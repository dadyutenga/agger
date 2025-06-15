<?php
session_start();
require_once "config.php";

// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Initialize debug log
$debug_log = [];

// Display logout message if set
if(isset($_GET["msg"]) && $_GET["msg"] === "logged_out") {
    $success_msg = "You have been successfully logged out.";
}

// Check if already logged in
if(isset($_SESSION["loggedin"]) && $_SESSION["loggedin"] === true){
    $debug_log[] = "User already logged in as: " . $_SESSION["user_type"];
    
    if($_SESSION["user_type"] === "doctor") {
        header("location: dashboard.php");
    } else if($_SESSION["user_type"] === "caretaker") {
        header("location: patient_details.php?id=" . $_SESSION["patient_id"]);
    } else {
        $debug_log[] = "Invalid user type detected: " . $_SESSION["user_type"];
        session_destroy();
        header("location: index.php");
    }
    exit;
}

$username = $password = "";
$username_err = $password_err = $login_err = "";
$user_type = isset($_GET["type"]) ? $_GET["type"] : (isset($_POST["user_type"]) ? $_POST["user_type"] : "doctor");

// Define error messages
$error_messages = [
    'login_required' => 'Please log in to access this page',
    'timeout' => 'Your session has expired. Please log in again',
    'unauthorized' => 'You are not authorized to access this page',
    'invalid_login' => 'Invalid username or password'
];

// Get error message if any
$error_msg = '';
if (isset($_GET['error']) && array_key_exists($_GET['error'], $error_messages)) {
    $error_msg = $error_messages[$_GET['error']];
}

if($_SERVER["REQUEST_METHOD"] == "POST"){
    $debug_log[] = "Login attempt - User Type: " . $user_type;
    
    // Validate username
    if(empty(trim($_POST["username"]))){
        $username_err = "Please enter username.";
        $debug_log[] = "Error: Empty username";
    } else{
        $username = trim($_POST["username"]);
        $debug_log[] = "Username provided: " . $username;
    }
    
    // Validate password
    if(empty(trim($_POST["password"]))){
        $password_err = "Please enter your password.";
        $debug_log[] = "Error: Empty password";
    } else{
        $password = trim($_POST["password"]);
        $debug_log[] = "Password provided: [HIDDEN]";
    }
    
    // Validate user type
    if(!in_array($user_type, ["doctor", "caretaker"])) {
        $login_err = "Invalid user type.";
        $debug_log[] = "Error: Invalid user type - " . $user_type;
    }
    
    // Validate credentials
    if(empty($username_err) && empty($password_err) && empty($login_err)){
        if($user_type === "doctor") {
            $sql = "SELECT id, username, password, full_name FROM doctors WHERE username = ?";
            $debug_log[] = "Attempting doctor login";
        } else {
            // Try both with and without 'care_' prefix for caretaker login
            $sql = "SELECT c.id, c.username, c.password, c.patient_id, p.full_name 
                   FROM caretaker_credentials c 
                   LEFT JOIN patients p ON c.patient_id = p.id 
                   WHERE c.username = ? OR c.username = ?";
            $debug_log[] = "Attempting caretaker login";
        }
        
        if($stmt = mysqli_prepare($conn, $sql)){
            if($user_type === "doctor") {
                mysqli_stmt_bind_param($stmt, "s", $param_username);
                $param_username = $username;
            } else {
                mysqli_stmt_bind_param($stmt, "ss", $username, $with_prefix);
                $with_prefix = "care_" . $username;
                $debug_log[] = "Checking usernames: " . $username . " and " . $with_prefix;
            }
            
            if(mysqli_stmt_execute($stmt)){
                mysqli_stmt_store_result($stmt);
                $debug_log[] = "SQL query executed. Found rows: " . mysqli_stmt_num_rows($stmt);
                
                if(mysqli_stmt_num_rows($stmt) == 1){
                    if($user_type === "doctor") {
                        mysqli_stmt_bind_result($stmt, $id, $username, $hashed_password, $full_name);
                    } else {
                        mysqli_stmt_bind_result($stmt, $id, $db_username, $hashed_password, $patient_id, $full_name);
                    }
                    
                    if(mysqli_stmt_fetch($stmt)){
                        $debug_log[] = "User found in database";
                        if(password_verify($password, $hashed_password)){
                            $debug_log[] = "Password verified successfully";
                            
                            // Clear any existing session
                            session_unset();
                            session_destroy();
                            session_start();
                            
                            // Set session variables
                            $_SESSION["loggedin"] = true;
                            $_SESSION["user_type"] = $user_type;
                            $_SESSION["id"] = $id;
                            $_SESSION["username"] = $user_type === "doctor" ? $username : $db_username;
                            $_SESSION["full_name"] = $full_name;
                            
                            if($user_type === "doctor") {
                                $debug_log[] = "Redirecting to doctor dashboard";
                                header("location: dashboard.php");
                            } else {
                                if($patient_id === null) {
                                    $login_err = "Caretaker has no assigned patient.";
                                    $debug_log[] = "Error: Caretaker has no assigned patient";
                                } else {
                                    $_SESSION["patient_id"] = $patient_id;
                                    $debug_log[] = "Redirecting to patient details. Patient ID: " . $patient_id;
                                    header("location: patient_details.php?id=" . $patient_id);
                                }
                            }
                            exit();
                        } else{
                            $login_err = "Invalid username or password.";
                            $debug_log[] = "Error: Password verification failed";
                        }
                    }
                } else{
                    $login_err = "Invalid username or password.";
                    $debug_log[] = "Error: No user found with username: " . $username;
                }
            } else{
                $login_err = "Oops! Something went wrong. Please try again later.";
                $debug_log[] = "Error: SQL execution failed - " . mysqli_error($conn);
            }
            mysqli_stmt_close($stmt);
        }
    }
}

// Add debug information to the page if there was a login attempt
$show_debug = !empty($login_err) || !empty($username_err) || !empty($password_err);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - Patient Monitoring System</title>
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
        .login-container {
            max-width: 400px;
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
        .user-type-selector {
            margin-bottom: 20px;
        }
        .user-type-selector .btn {
            width: 50%;
            padding: 10px;
        }
        .form-floating {
            margin-bottom: 15px;
        }
        .login-info {
            margin-top: 20px;
            padding: 15px;
            background: #f8f9fa;
            border-radius: 5px;
            font-size: 0.9rem;
        }
        .login-info h6 {
            color: var(--hospital-blue);
            margin-bottom: 10px;
        }
        .debug-info {
            margin-top: 20px;
            padding: 15px;
            background: #f8f9fa;
            border: 1px solid #dee2e6;
            border-radius: 5px;
            font-family: monospace;
            font-size: 12px;
            white-space: pre-wrap;
        }
        .debug-info h6 {
            color: #dc3545;
            margin-bottom: 10px;
        }
        .error-message {
            color: #dc3545;
            margin-bottom: 1rem;
            text-align: center;
        }
    </style>
</head>
<body>
    <div class="login-container">
        <div class="logo">
            <h1>Patient Monitoring System</h1>
            <p class="text-muted">Please login to continue</p>
        </div>

        <?php 
        if(!empty($login_err)){
            echo '<div class="alert alert-danger">' . $login_err . '</div>';
        }
        if(isset($success_msg)){
            echo '<div class="alert alert-success">' . $success_msg . '</div>';
        }
        
        // Display debug information if there were errors
        if($show_debug && !empty($debug_log)):
        ?>
        <div class="debug-info">
            <h6>Debug Information:</h6>
            <div class="debug-log">
                <?php 
                foreach($debug_log as $log) {
                    echo htmlspecialchars($log) . "\n";
                }
                ?>
            </div>
        </div>
        <?php endif; ?>

        <?php 
        if (!empty($error_msg)) {
            echo '<div class="error-message mt-3">' . htmlspecialchars($error_msg) . '</div>';
        }
        ?>

        <form action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>" method="post">
            <div class="user-type-selector btn-group w-100 mb-4">
                <input type="radio" class="btn-check" name="user_type" id="doctor" value="doctor" 
                       <?php echo $user_type === 'doctor' ? 'checked' : ''; ?> autocomplete="off">
                <label class="btn btn-outline-primary" for="doctor">Doctor</label>

                <input type="radio" class="btn-check" name="user_type" id="caretaker" value="caretaker" 
                       <?php echo $user_type === 'caretaker' ? 'checked' : ''; ?> autocomplete="off">
                <label class="btn btn-outline-primary" for="caretaker">Caretaker</label>
            </div>

            <div class="form-floating mb-3">
                <input type="text" name="username" class="form-control <?php echo (!empty($username_err)) ? 'is-invalid' : ''; ?>" 
                       value="<?php echo $username; ?>" id="username" placeholder="Username">
                <label for="username">Username</label>
                <span class="invalid-feedback"><?php echo $username_err; ?></span>
            </div>    

            <div class="form-floating mb-4">
                <input type="password" name="password" class="form-control <?php echo (!empty($password_err)) ? 'is-invalid' : ''; ?>" 
                       id="password" placeholder="Password">
                <label for="password">Password</label>
                <span class="invalid-feedback"><?php echo $password_err; ?></span>
            </div>

            <div class="d-grid">
                <button class="btn btn-primary btn-lg" type="submit">Login</button>
            </div>

            <div class="login-link text-center mt-3">
                Don't have an account? <a href="register.php">Register here</a>
            </div>

            <div class="login-info mt-4">
                <h6>Access Information:</h6>
                <div id="doctorInfo" <?php echo $user_type === 'caretaker' ? 'style="display:none;"' : ''; ?>>
                    <p class="mb-1"><strong>Doctor Access:</strong></p>
                    <ul class="mb-0 ps-3">
                        <li>Full dashboard access</li>
                        <li>Patient management</li>
                        <li>Analysis and reports</li>
                    </ul>
                </div>
                <div id="caretakerInfo" <?php echo $user_type === 'doctor' ? 'style="display:none;"' : ''; ?>>
                    <p class="mb-1"><strong>Caretaker Access:</strong></p>
                    <ul class="mb-0 ps-3">
                        <li>View assigned patient only</li>
                        <li>Monitor vital signs</li>
                        <li>View patient details</li>
                    </ul>
                </div>
            </div>
        </form>

        <div class="text-center mt-3">
            <a href="home.php" class="text-muted text-decoration-none">
                <small>← Back to Home</small>
            </a>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Toggle access information based on selected user type
        document.querySelectorAll('input[name="user_type"]').forEach(radio => {
            radio.addEventListener('change', function() {
                document.getElementById('doctorInfo').style.display = this.value === 'doctor' ? 'block' : 'none';
                document.getElementById('caretakerInfo').style.display = this.value === 'caretaker' ? 'block' : 'none';
            });
        });
    </script>
</body>
</html> 