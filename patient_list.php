<?php
session_start();
require_once "config.php";

// Check if user is logged in
if(!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true){
    header("location: index.php");
    exit;
}

// Handle patient deletion
if(isset($_POST['delete_patient']) && !empty($_POST['patient_id'])) {
    $sql = "UPDATE patients SET is_active = 0, deleted_at = CURRENT_TIMESTAMP WHERE id = ? AND doctor_id = ?";
    if($stmt = mysqli_prepare($conn, $sql)) {
        mysqli_stmt_bind_param($stmt, "ii", $_POST['patient_id'], $_SESSION['id']);
        if(mysqli_stmt_execute($stmt)) {
            $_SESSION['success'] = "Patient successfully deactivated.";
        } else {
            $_SESSION['error'] = "Error deactivating patient.";
        }
        mysqli_stmt_close($stmt);
    }
    header("location: patient_list.php");
    exit();
}

// Handle patient restoration
if(isset($_POST['restore_patient']) && !empty($_POST['patient_id'])) {
    $sql = "UPDATE patients SET is_active = 1, deleted_at = NULL WHERE id = ? AND doctor_id = ?";
    if($stmt = mysqli_prepare($conn, $sql)) {
        mysqli_stmt_bind_param($stmt, "ii", $_POST['patient_id'], $_SESSION['id']);
        if(mysqli_stmt_execute($stmt)) {
            $_SESSION['success'] = "Patient successfully restored.";
        } else {
            $_SESSION['error'] = "Error restoring patient.";
        }
        mysqli_stmt_close($stmt);
    }
    header("location: patient_list.php");
    exit();
}

// Get filter parameters
$status_filter = isset($_GET['status']) ? $_GET['status'] : 'active';
$search = isset($_GET['search']) ? $_GET['search'] : '';

// Prepare the SQL query based on filters
$sql = "SELECT * FROM patients WHERE doctor_id = ?";
if($status_filter === 'active') {
    $sql .= " AND is_active = 1";
} elseif($status_filter === 'inactive') {
    $sql .= " AND is_active = 0";
}
if(!empty($search)) {
    $sql .= " AND (full_name LIKE ? OR device_id LIKE ?)";
}
$sql .= " ORDER BY full_name ASC";

// Prepare and execute the query
$stmt = mysqli_prepare($conn, $sql);
if(!empty($search)) {
    $search_param = "%$search%";
    if($status_filter === 'all') {
        mysqli_stmt_bind_param($stmt, "iss", $_SESSION['id'], $search_param, $search_param);
    } else {
        mysqli_stmt_bind_param($stmt, "iss", $_SESSION['id'], $search_param, $search_param);
    }
} else {
    mysqli_stmt_bind_param($stmt, "i", $_SESSION['id']);
}
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Patient List - Patient Monitoring System</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.7.2/font/bootstrap-icons.css" rel="stylesheet">
    <style>
        .patient-card {
            transition: all 0.3s ease;
        }
        .patient-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
        }
        .patient-image {
            width: 100px;
            height: 100px;
            object-fit: cover;
            border-radius: 50%;
        }
        .status-badge {
            position: absolute;
            top: 10px;
            right: 10px;
        }
        .search-box {
            max-width: 300px;
        }
    </style>
</head>
<body class="bg-light">
    <?php include 'navbar.php'; ?>

    <div class="container py-4">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h2>Patient List</h2>
            <a href="register_patient.php" class="btn btn-primary">
                <i class="bi bi-plus-circle"></i> Add New Patient
            </a>
        </div>

        <?php 
        if(isset($_SESSION["error"])) { 
            echo '<div class="alert alert-danger">' . $_SESSION["error"] . '</div>';
            unset($_SESSION["error"]);
        }
        if(isset($_SESSION["success"])) { 
            echo '<div class="alert alert-success">' . $_SESSION["success"] . '</div>';
            unset($_SESSION["success"]);
        }
        ?>

        <div class="card mb-4">
            <div class="card-body">
                <div class="row align-items-center">
                    <div class="col-md-6">
                        <div class="btn-group" role="group">
                            <a href="?status=active<?php echo !empty($search) ? '&search='.$search : ''; ?>" 
                               class="btn btn-outline-primary <?php echo $status_filter === 'active' ? 'active' : ''; ?>">
                                Active Patients
                            </a>
                            <a href="?status=inactive<?php echo !empty($search) ? '&search='.$search : ''; ?>" 
                               class="btn btn-outline-primary <?php echo $status_filter === 'inactive' ? 'active' : ''; ?>">
                                Inactive Patients
                            </a>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <form class="d-flex justify-content-end">
                            <input type="hidden" name="status" value="<?php echo htmlspecialchars($status_filter); ?>">
                            <div class="input-group search-box">
                                <input type="text" name="search" class="form-control" placeholder="Search patients..." 
                                       value="<?php echo htmlspecialchars($search); ?>">
                                <button class="btn btn-outline-secondary" type="submit">
                                    <i class="bi bi-search"></i>
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>

        <div class="row">
            <?php while($patient = mysqli_fetch_assoc($result)): ?>
                <div class="col-md-6 col-lg-4 mb-4">
                    <div class="card patient-card h-100">
                        <div class="card-body position-relative">
                            <span class="status-badge badge bg-<?php 
                                echo $patient['status'] === 'Critical' ? 'danger' : 
                                    ($patient['status'] === 'Stable' ? 'success' : 
                                    ($patient['status'] === 'Recovering' ? 'info' : 'warning')); 
                            ?>">
                                <?php echo htmlspecialchars($patient['status']); ?>
                            </span>
                            
                            <div class="text-center mb-3">
                                <img src="<?php echo !empty($patient['image_path']) ? htmlspecialchars($patient['image_path']) : 'images/default-patient.png'; ?>" 
                                     class="patient-image mb-2" alt="Patient Photo">
                                <h5 class="card-title mb-0"><?php echo htmlspecialchars($patient['full_name']); ?></h5>
                                <small class="text-muted">ID: <?php echo htmlspecialchars($patient['device_id']); ?></small>
                            </div>
                            
                            <div class="mb-3">
                                <p class="mb-1"><strong>Age:</strong> <?php echo htmlspecialchars($patient['age']); ?></p>
                                <p class="mb-1"><strong>Location:</strong> <?php echo htmlspecialchars($patient['region'] . ', ' . $patient['ward']); ?></p>
                                <p class="mb-1"><strong>Registration:</strong> <?php echo date('M d, Y', strtotime($patient['registration_date'])); ?></p>
                            </div>

                            <div class="d-flex justify-content-between">
                                <a href="patient_details.php?id=<?php echo $patient['id']; ?>" class="btn btn-outline-primary btn-sm">
                                    <i class="bi bi-graph-up"></i> View Details
                                </a>
                                
                                <?php if($patient['is_active']): ?>
                                    <form method="post" style="display: inline;" 
                                          onsubmit="return confirm('Are you sure you want to deactivate this patient?');">
                                        <input type="hidden" name="patient_id" value="<?php echo $patient['id']; ?>">
                                        <button type="submit" name="delete_patient" class="btn btn-outline-danger btn-sm">
                                            <i class="bi bi-person-x"></i> Deactivate
                                        </button>
                                    </form>
                                <?php else: ?>
                                    <form method="post" style="display: inline;">
                                        <input type="hidden" name="patient_id" value="<?php echo $patient['id']; ?>">
                                        <button type="submit" name="restore_patient" class="btn btn-outline-success btn-sm">
                                            <i class="bi bi-person-check"></i> Restore
                                        </button>
                                    </form>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
            <?php endwhile; ?>
            
            <?php if(mysqli_num_rows($result) === 0): ?>
                <div class="col-12">
                    <div class="alert alert-info text-center">
                        No patients found.
                        <?php if(!empty($search)): ?>
                            <br>Try adjusting your search criteria.
                        <?php endif; ?>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html> 