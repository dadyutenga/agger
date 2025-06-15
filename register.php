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

$user_type = 'doctor';
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

        <form action="register_doctor_process.php" method="post" id="doctorForm">
            <div class="form-floating mb-3">
                <input type="text" class="form-control" id="full_name" name="full_name" required>
                <label for="full_name">Full Name</label>
            </div>
            <div class="form-floating mb-3">
                <input type="email" class="form-control" id="email" name="email" required>
                <label for="email">Email Address</label>
            </div>
            <div class="form-floating mb-3">
                <input type="text" class="form-control" id="username" name="username" required>
                <label for="username">Username</label>
            </div>
            <div class="form-floating mb-3">
                <input type="password" class="form-control" id="password" name="password" required>
                <label for="password">Password</label>
            </div>
            <div class="form-floating mb-4">
                <input type="password" class="form-control" id="confirm_password" name="confirm_password" required>
                <label for="confirm_password">Confirm Password</label>
            </div>
            <div class="form-floating mb-4">
                <input type="text" class="form-control" id="phone" name="phone">
                <label for="phone">Phone Number (Optional)</label>
            </div>
            <div class="d-grid">
                <button type="submit" class="btn btn-primary btn-lg">Register as Doctor</button>
            </div>
        </form>

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
                    if (document.getElementById('password').value !== document.getElementById('confirm_password').value) {
                        e.preventDefault();
                        alert('Passwords do not match');
                        return false;
                    }
                    if (document.getElementById('password').value.length < 6) {
                        e.preventDefault();
                        alert('Password must be at least 6 characters long');
                        return false;
                    }
                    return true;
                });
            }
        });
    </script>
</body>
</html>
