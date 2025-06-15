<?php
session_start();
require_once "config.php";

// Check if user is logged in
if(!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true){
    header("location: index.php");
    exit;
}

// Process form submission
if($_SERVER["REQUEST_METHOD"] == "POST") {
    $errors = array();
    
    // Validate input
    if(empty($_POST['full_name'])) {
        $errors[] = "Full name is required";
    }
    if(empty($_POST['email'])) {
        $errors[] = "Email is required";
    }
    if(empty($_POST['phone'])) {
        $errors[] = "Phone number is required";
    }
    
    // Update password if provided
    if(!empty($_POST['new_password'])) {
        if(strlen($_POST['new_password']) < 6) {
            $errors[] = "Password must be at least 6 characters";
        } else {
            // Verify current password
            $sql = "SELECT password FROM doctors WHERE id = ?";
            if($stmt = mysqli_prepare($conn, $sql)) {
                mysqli_stmt_bind_param($stmt, "i", $_SESSION["id"]);
                mysqli_stmt_execute($stmt);
                $result = mysqli_stmt_get_result($stmt);
                $row = mysqli_fetch_assoc($result);
                
                if(!password_verify($_POST['current_password'], $row['password'])) {
                    $errors[] = "Current password is incorrect";
                }
            }
        }
    }
    
    // If no errors, update profile
    if(empty($errors)) {
        $sql = "UPDATE doctors SET full_name = ?, email = ?, phone = ?";
        $params = array($_POST['full_name'], $_POST['email'], $_POST['phone']);
        $types = "sss";
        
        if(!empty($_POST['new_password'])) {
            $sql .= ", password = ?";
            $params[] = password_hash($_POST['new_password'], PASSWORD_DEFAULT);
            $types .= "s";
        }
        
        $sql .= " WHERE id = ?";
        $params[] = $_SESSION["id"];
        $types .= "i";
        
        if($stmt = mysqli_prepare($conn, $sql)) {
            mysqli_stmt_bind_param($stmt, $types, ...$params);
            if(mysqli_stmt_execute($stmt)) {
                $_SESSION["full_name"] = $_POST['full_name'];
                $_SESSION["success"] = "Profile updated successfully!";
                header("location: profile.php");
                exit();
            } else {
                $errors[] = "Something went wrong. Please try again later.";
            }
            mysqli_stmt_close($stmt);
        }
    }
}

// Get doctor's current information
$sql = "SELECT * FROM doctors WHERE id = ?";
if($stmt = mysqli_prepare($conn, $sql)) {
    mysqli_stmt_bind_param($stmt, "i", $_SESSION["id"]);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $doctor = mysqli_fetch_array($result);
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Doctor Profile - Patient Monitoring System</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        .profile-container {
            max-width: 800px;
            margin: 30px auto;
            padding: 20px;
            background: white;
            border-radius: 10px;
            box-shadow: 0 0 10px rgba(0,0,0,0.1);
        }
        .stats-card {
            background: white;
            border-radius: 10px;
            padding: 20px;
            margin-bottom: 20px;
            box-shadow: 0 0 10px rgba(0,0,0,0.1);
        }
        .stat-value {
            font-size: 24px;
            font-weight: bold;
            color: #2c3e50;
        }
        .stat-label {
            color: #7f8c8d;
            font-size: 14px;
        }
    </style>
</head>
<body class="bg-light">
    <?php include 'navbar.php'; ?>

    <div class="container">
        <div class="profile-container">
            <h2 class="mb-4">Doctor Profile</h2>
            
            <?php 
            if(isset($_SESSION["error"])) { 
                echo '<div class="alert alert-danger">' . $_SESSION["error"] . '</div>';
                unset($_SESSION["error"]);
            }
            if(isset($_SESSION["success"])) { 
                echo '<div class="alert alert-success">' . $_SESSION["success"] . '</div>';
                unset($_SESSION["success"]);
            }
            if(!empty($errors)) {
                echo '<div class="alert alert-danger"><ul class="mb-0"><li>' . implode('</li><li>', $errors) . '</li></ul></div>';
            }
            ?>

            <div class="row mb-4">
                <div class="col-md-4">
                    <div class="stats-card text-center">
                        <div class="stat-value"><?php
                            $result = mysqli_query($conn, "SELECT COUNT(*) as total FROM patients WHERE doctor_id = " . $_SESSION['id'] . " AND is_active = 1");
                            $row = mysqli_fetch_assoc($result);
                            echo $row['total'];
                        ?></div>
                        <div class="stat-label">Active Patients</div>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="stats-card text-center">
                        <div class="stat-value"><?php
                            $result = mysqli_query($conn, "SELECT COUNT(*) as total FROM patients WHERE doctor_id = " . $_SESSION['id'] . " AND status = 'Critical' AND is_active = 1");
                            $row = mysqli_fetch_assoc($result);
                            echo $row['total'];
                        ?></div>
                        <div class="stat-label">Critical Patients</div>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="stats-card text-center">
                        <div class="stat-value"><?php
                            $result = mysqli_query($conn, "SELECT COUNT(*) as total FROM patients WHERE doctor_id = " . $_SESSION['id'] . " AND DATE(created_at) = CURDATE() AND is_active = 1");
                            $row = mysqli_fetch_assoc($result);
                            echo $row['total'];
                        ?></div>
                        <div class="stat-label">New Today</div>
                    </div>
                </div>
            </div>

            <form method="post" action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>">
                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label class="form-label">Full Name</label>
                        <input type="text" name="full_name" class="form-control" required value="<?php echo htmlspecialchars($doctor['full_name']); ?>">
                    </div>
                    <div class="col-md-6 mb-3">
                        <label class="form-label">Username</label>
                        <input type="text" class="form-control" value="<?php echo htmlspecialchars($doctor['username']); ?>" disabled>
                    </div>
                </div>

                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label class="form-label">Email</label>
                        <input type="email" name="email" class="form-control" required value="<?php echo htmlspecialchars($doctor['email']); ?>">
                    </div>
                    <div class="col-md-6 mb-3">
                        <label class="form-label">Phone</label>
                        <input type="tel" name="phone" class="form-control" required value="<?php echo htmlspecialchars($doctor['phone']); ?>">
                    </div>
                </div>

                <h4 class="mt-4 mb-3">Change Password</h4>
                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label class="form-label">Current Password</label>
                        <input type="password" name="current_password" class="form-control">
                    </div>
                    <div class="col-md-6 mb-3">
                        <label class="form-label">New Password</label>
                        <input type="password" name="new_password" class="form-control">
                    </div>
                </div>

                <button type="submit" class="btn btn-primary">Update Profile</button>
            </form>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html> 