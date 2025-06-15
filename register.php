<?php
session_start();
require_once "config.php";

// Redirect if already logged in
if(isset($_SESSION["loggedin"]) && $_SESSION["loggedin"] === true){
    if($_SESSION["user_type"] === "doctor") {
        header("location: dashboard.php");
    } else {
        header("location: patient_details.php?id=" . $_SESSION["patient_id"]);
    }
    exit;
}

$user_type = isset($_GET["type"]) ? $_GET["type"] : "doctor";
$success_msg = "";
$error_msg = "";

// Check for success/error messages from registration
if(isset($_GET["status"])) {
    if($_GET["status"] === "success") {
        $success_msg = "Registration successful! You can now login.";
    } elseif(isset($_GET["error"])) {
        $error_msg = "Error: " . htmlspecialchars($_GET["error"]);
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Register - Patient Monitoring System</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        :root {
            --hospital-blue: #0055a4;
        }
        body {
            background: #f8f9fa;
            min-height: 100vh;
            display: flex;
            align-items: center;
            padding: 20px;
        }
        .register-container {
            max-width: 600px;
            margin: 0 auto;
            padding: 30px;
            background: white;
            border-radius: 10px;
            box-shadow: 0 0 20px rgba(0,0,0,0.1);
        }
        .logo {
            text-align: center;
            margin-bottom: 30px;
        }
        .logo h1 {
            color: var(--hospital-blue);
            font-size: 24px;
            margin-bottom: 10px;
        }
        .nav-tabs {
            margin-bottom: 20px;
            border-bottom: 1px solid #dee2e6;
        }
        .nav-tabs .nav-link {
            color: #495057;
            font-weight: 500;
            border: none;
            padding: 10px 20px;
            margin-right: 5px;
            border-radius: 4px 4px 0 0;
        }
        .nav-tabs .nav-link.active {
            color: var(--hospital-blue);
            background-color: #f8f9fa;
            border: 1px solid #dee2e6;
            border-bottom-color: transparent;
        }
        .nav-tabs .nav-link:not(.active):hover {
            border-color: transparent;
            background-color: #f1f1f1;
        }
        .tab-content {
            padding: 20px 0;
        }
        .form-floating {
            margin-bottom: 15px;
        }
        .login-link {
            text-align: center;
            margin-top: 20px;
        }
        .error-message {
            color: #dc3545;
            margin-bottom: 1rem;
            text-align: center;
        }
        .success-message {
            color: #198754;
            margin-bottom: 1rem;
            text-align: center;
        }
    </style>
</head>
<body>
    <div class="register-container">
        <div class="logo">
            <h1>Patient Monitoring System</h1>
            <p class="text-muted">Create a new account</p>
        </div>

        <?php if($success_msg): ?>
            <div class="alert alert-success"><?php echo $success_msg; ?></div>
        <?php endif; ?>
        
        <?php if($error_msg): ?>
            <div class="alert alert-danger"><?php echo $error_msg; ?></div>
        <?php endif; ?>

        <ul class="nav nav-tabs" id="registerTabs" role="tablist">
            <li class="nav-item" role="presentation">
                <button class="nav-link <?php echo $user_type === 'doctor' ? 'active' : ''; ?>" 
                        id="doctor-tab" data-bs-toggle="tab" data-bs-target="#doctor" type="button" 
                        role="tab" aria-controls="doctor" aria-selected="<?php echo $user_type === 'doctor' ? 'true' : 'false'; ?>">
                    Doctor
                </button>
            </li>
            <li class="nav-item" role="presentation">
                <button class="nav-link <?php echo $user_type === 'caretaker' ? 'active' : ''; ?>" 
                        id="caretaker-tab" data-bs-toggle="tab" data-bs-target="#caretaker" type="button" 
                        role="tab" aria-controls="caretaker" aria-selected="<?php echo $user_type === 'caretaker' ? 'true' : 'false'; ?>">
                    Caretaker
                </button>
            </li>
        </ul>

        <div class="tab-content" id="registerTabsContent">
            <!-- Doctor Registration Form -->
            <div class="tab-pane fade <?php echo $user_type === 'doctor' ? 'show active' : ''; ?>" id="doctor" role="tabpanel" aria-labelledby="doctor-tab">
                <form action="register_doctor_process.php" method="post" id="doctorForm">
                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-floating mb-3">
                                <input type="text" class="form-control" id="dfirst_name" name="first_name" required>
                                <label for="dfirst_name">First Name</label>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-floating mb-3">
                                <input type="text" class="form-control" id="dlast_name" name="last_name" required>
                                <label for="dlast_name">Last Name</label>
                            </div>
                        </div>
                    </div>
                    <div class="form-floating mb-3">
                        <input type="email" class="form-control" id="demail" name="email" required>
                        <label for="demail">Email Address</label>
                    </div>
                    <div class="form-floating mb-3">
                        <input type="text" class="form-control" id="dusername" name="username" required>
                        <label for="dusername">Username</label>
                    </div>
                    <div class="form-floating mb-3">
                        <input type="password" class="form-control" id="dpassword" name="password" required>
                        <label for="dpassword">Password</label>
                    </div>
                    <div class="form-floating mb-4">
                        <input type="password" class="form-control" id="dconfirm_password" name="confirm_password" required>
                        <label for="dconfirm_password">Confirm Password</label>
                    </div>
                    <div class="d-grid">
                        <button type="submit" class="btn btn-primary btn-lg">Register as Doctor</button>
                    </div>
                </form>
            </div>

            <!-- Caretaker Registration Form -->
            <div class="tab-pane fade <?php echo $user_type === 'caretaker' ? 'show active' : ''; ?>" id="caretaker" role="tabpanel" aria-labelledby="caretaker-tab">
                <form action="register_caretaker_process.php" method="post" id="caretakerForm">
                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-floating mb-3">
                                <input type="text" class="form-control" id="cfirst_name" name="first_name" required>
                                <label for="cfirst_name">First Name</label>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-floating mb-3">
                                <input type="text" class="form-control" id="clast_name" name="last_name" required>
                                <label for="clast_name">Last Name</label>
                            </div>
                        </div>
                    </div>
                    <div class="form-floating mb-3">
                        <input type="email" class="form-control" id="cemail" name="email" required>
                        <label for="cemail">Email Address</label>
                    </div>
                    <div class="form-floating mb-3">
                        <input type="text" class="form-control" id="cusername" name="username" required>
                        <label for="cusername">Username</label>
                    </div>
                    <div class="form-floating mb-3">
                        <input type="password" class="form-control" id="cpassword" name="password" required>
                        <label for="cpassword">Password</label>
                    </div>
                    <div class="form-floating mb-4">
                        <input type="password" class="form-control" id="cconfirm_password" name="confirm_password" required>
                        <label for="cconfirm_password">Confirm Password</label>
                    </div>
                    <div class="d-grid">
                        <button type="submit" class="btn btn-primary btn-lg">Register as Caretaker</button>
                    </div>
                </form>
            </div>
        </div>

        <div class="login-link">
            Already have an account? <a href="index.php">Login here</a>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Client-side form validation
        document.addEventListener('DOMContentLoaded', function() {
            // Doctor form validation
            const doctorForm = document.getElementById('doctorForm');
            if (doctorForm) {
                doctorForm.addEventListener('submit', function(e) {
                    if (document.getElementById('dpassword').value !== document.getElementById('dconfirm_password').value) {
                        e.preventDefault();
                        alert('Passwords do not match');
                        return false;
                    }
                    return true;
                });
            }


            // Caretaker form validation
            const caretakerForm = document.getElementById('caretakerForm');
            if (caretakerForm) {
                caretakerForm.addEventListener('submit', function(e) {
                    if (document.getElementById('cpassword').value !== document.getElementById('cconfirm_password').value) {
                        e.preventDefault();
                        alert('Passwords do not match');
                        return false;
                    }
                    return true;
                });
            }
        });
    </script>
</body>
</html>
