<?php
session_start();
require_once "config.php";

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

// Get patient details
$sql = "SELECT * FROM patients WHERE id = ? AND doctor_id = ?";
if($stmt = mysqli_prepare($conn, $sql)){
    mysqli_stmt_bind_param($stmt, "ii", $patient_id, $_SESSION["id"]);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $patient = mysqli_fetch_array($result);
    
    if(!$patient){
        header("location: dashboard.php");
        exit;
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($patient['full_name']); ?> - Patient Details</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/boxicons@2.0.7/css/boxicons.min.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        body {
            padding-top: 56px; /* Height of navbar */
        }
        .sidebar {
            width: 300px;
            height: calc(100vh - 56px);
            position: fixed;
            left: 0;
            top: 56px; /* Start below navbar */
            background: white;
            padding: 20px;
            overflow-y: auto;
            border-right: 1px solid #dee2e6;
            z-index: 100;
        }
        .main-content {
            margin-left: 300px;
            padding: 20px;
            margin-top: 0; /* Remove top margin since we have padding-top on body */
        }
        .navbar {
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            z-index: 1030;
            background: white;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        .patient-image {
            width: 150px;
            height: 150px;
            border-radius: 75px;
            object-fit: cover;
            margin: 0 auto 20px;
            display: block;
        }
        .profile-info {
            margin-bottom: 15px;
            padding-bottom: 15px;
            border-bottom: 1px solid #dee2e6;
        }
        .profile-info:last-child {
            border-bottom: none;
        }
        .vital-sign {
            text-align: center;
            padding: 15px;
            border-radius: 10px;
            margin: 10px 0;
            transition: transform 0.3s;
        }
        .vital-sign:hover {
            transform: translateY(-5px);
        }
        .vital-sign h5 {
            margin-bottom: 10px;
            color: #666;
        }
        .vital-sign h2 {
            margin: 0;
            font-size: 2rem;
        }
        .heart-rate { 
            background: rgba(231, 76, 60, 0.1);
            border-left: 4px solid #e74c3c;
        }
        .spo2 { 
            background: rgba(52, 152, 219, 0.1);
            border-left: 4px solid #3498db;
        }
        .temperature { 
            background: rgba(46, 204, 113, 0.1);
            border-left: 4px solid #2ecc71;
        }
        .chart-container {
            background: white;
            border-radius: 10px;
            padding: 20px;
            margin-bottom: 20px;
            box-shadow: 0 0 10px rgba(0,0,0,0.1);
            height: 300px;
        }
        .status-badge {
            font-size: 1rem;
            padding: 0.5rem 1rem;
        }
    </style>
</head>
<body class="bg-light">
    <?php include 'navbar.php'; ?>

    <div class="sidebar">
        <img src="<?php echo !empty($patient['image_path']) ? htmlspecialchars($patient['image_path']) : 'images/default-patient.png'; ?>" 
             class="patient-image" alt="Patient Photo">
        
        <div class="text-center mb-3">
            <span class="badge bg-<?php 
                echo $patient['status'] === 'Critical' ? 'danger' : 
                    ($patient['status'] === 'Stable' ? 'success' : 
                    ($patient['status'] === 'Recovering' ? 'info' : 'warning')); 
            ?> status-badge"><?php echo htmlspecialchars($patient['status']); ?></span>
        </div>
        
        <div class="profile-info">
            <h5>Personal Information</h5>
            <p><strong>Name:</strong> <?php echo htmlspecialchars($patient['full_name']); ?></p>
            <p><strong>Age:</strong> <?php echo htmlspecialchars($patient['age']); ?></p>
            <p><strong>Gender:</strong> <?php echo htmlspecialchars($patient['gender']); ?></p>
            <p><strong>Registration Date:</strong> <?php echo date('d/m/Y', strtotime($patient['registration_date'])); ?></p>
        </div>

        <div class="profile-info">
            <h5>Location</h5>
            <p><strong>Region:</strong> <?php echo htmlspecialchars($patient['region']); ?></p>
            <p><strong>Ward:</strong> <?php echo htmlspecialchars($patient['ward']); ?></p>
            <p><strong>Street:</strong> <?php echo htmlspecialchars($patient['street']); ?></p>
        </div>

        <div class="profile-info">
            <h5>Emergency Contact</h5>
            <p><strong>Name:</strong> <?php echo htmlspecialchars($patient['emergency_contact']); ?></p>
            <p><strong>Phone:</strong> <?php echo htmlspecialchars($patient['emergency_phone']); ?></p>
        </div>

        <div class="profile-info">
            <h5>Device Information</h5>
            <p><strong>Device ID:</strong> <?php echo htmlspecialchars($patient['device_id']); ?></p>
        </div>
    </div>

    <div class="main-content">
        <h2 class="mb-4">Real-time Monitoring</h2>

        <!-- Vital Signs -->
        <div class="row mb-4">
            <div class="col-md-4">
                <div class="vital-sign heart-rate">
                    <h5>Heart Rate</h5>
                    <h2 id="heart-rate">-- BPM</h2>
                </div>
            </div>
            <div class="col-md-4">
                <div class="vital-sign spo2">
                    <h5>SpO2</h5>
                    <h2 id="spo2">--%</h2>
                </div>
            </div>
            <div class="col-md-4">
                <div class="vital-sign temperature">
                    <h5>Temperature</h5>
                    <h2 id="temperature">--°C</h2>
                </div>
            </div>
        </div>

        <!-- Charts -->
        <div class="row">
            <div class="col-md-6 mb-4">
                <div class="card">
                    <div class="card-body">
                        <h5 class="card-title">Heart Rate Trend</h5>
                        <div class="chart-container">
                            <canvas id="heartRateChart"></canvas>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-md-6 mb-4">
                <div class="card">
                    <div class="card-body">
                        <h5 class="card-title">SpO2 Trend</h5>
                        <div class="chart-container">
                            <canvas id="spo2Chart"></canvas>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-md-6 mb-4">
                <div class="card">
                    <div class="card-body">
                        <h5 class="card-title">Temperature Trend</h5>
                        <div class="chart-container">
                            <canvas id="temperatureChart"></canvas>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-md-6 mb-4">
                <div class="card">
                    <div class="card-body">
                        <h5 class="card-title">Alerts History</h5>
                        <div class="chart-container">
                            <canvas id="alertsChart"></canvas>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
        const chartOptions = {
            responsive: true,
            maintainAspectRatio: false,
            scales: {
                y: {
                    beginAtZero: true,
                    grid: {
                        drawBorder: false
                    }
                },
                x: {
                    grid: {
                        display: false
                    },
                    ticks: {
                        maxTicksLimit: 5,
                        autoSkip: true,
                        maxRotation: 0
                    }
                }
            },
            plugins: {
                legend: {
                    display: false
                }
            }
        };

        const heartRateChart = new Chart(document.getElementById('heartRateChart').getContext('2d'), {
            type: 'line',
            data: {
                labels: [],
                datasets: [{
                    label: 'Heart Rate (BPM)',
                    borderColor: '#e74c3c',
                    borderWidth: 2,
                    data: [],
                    fill: true,
                    backgroundColor: 'rgba(231, 76, 60, 0.1)'
                }]
            },
            options: chartOptions
        });

        const spo2Chart = new Chart(document.getElementById('spo2Chart').getContext('2d'), {
            type: 'line',
            data: {
                labels: [],
                datasets: [{
                    label: 'SpO2 (%)',
                    borderColor: '#3498db',
                    borderWidth: 2,
                    data: [],
                    fill: true,
                    backgroundColor: 'rgba(52, 152, 219, 0.1)'
                }]
            },
            options: chartOptions
        });

        const temperatureChart = new Chart(document.getElementById('temperatureChart').getContext('2d'), {
            type: 'line',
            data: {
                labels: [],
                datasets: [{
                    label: 'Temperature (°C)',
                    borderColor: '#2ecc71',
                    borderWidth: 2,
                    data: [],
                    fill: true,
                    backgroundColor: 'rgba(46, 204, 113, 0.1)'
                }]
            },
            options: chartOptions
        });

        const alertsChart = new Chart(document.getElementById('alertsChart').getContext('2d'), {
            type: 'line',
            data: {
                labels: [],
                datasets: [{
                    label: 'Alerts',
                    borderColor: '#f1c40f',
                    borderWidth: 2,
                    data: [],
                    fill: true,
                    backgroundColor: 'rgba(241, 196, 15, 0.1)'
                }]
            },
            options: chartOptions
        });

        function updateCharts() {
            fetch(`get_vital_signs.php?patient_id=<?php echo $patient_id; ?>`)
                .then(response => response.json())
                .then(data => {
                    const currentTime = new Date().toLocaleTimeString();

                    document.getElementById('heart-rate').textContent = data.heart_rate + ' BPM';
                    document.getElementById('spo2').textContent = data.spo2 + '%';
                    document.getElementById('temperature').textContent = data.temperature + '°C';

                    [heartRateChart, spo2Chart, temperatureChart, alertsChart].forEach(chart => {
                        chart.data.labels.push(currentTime);
                        if (chart.data.labels.length > 15) {
                            chart.data.labels.shift();
                            chart.data.datasets[0].data.shift();
                        }
                    });

                    heartRateChart.data.datasets[0].data.push(data.heart_rate);
                    spo2Chart.data.datasets[0].data.push(data.spo2);
                    temperatureChart.data.datasets[0].data.push(data.temperature);
                    alertsChart.data.datasets[0].data.push(data.alerts);

                    [heartRateChart, spo2Chart, temperatureChart, alertsChart].forEach(chart => chart.update('none'));
                });
        }

        setInterval(updateCharts, 5000);
        updateCharts();
    </script>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html> 