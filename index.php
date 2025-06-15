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
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        :root {
            --forest-green: #358927;
            --wattle-green: #D7DE50;
            --white: #ffffff;
            --light-gray: #f8f9fa;
            --dark-text: #2c3e50;
            --shadow: rgba(53, 137, 39, 0.15);
            --error-red: #dc3545;
            --success-green: #198754;
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            background: linear-gradient(135deg, var(--forest-green) 0%, #2d7a23 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            position: relative;
            overflow-x: hidden;
            padding: 20px;
        }

        /* Animated background elements */
        body::before {
            content: '';
            position: absolute;
            width: 150px;
            height: 150px;
            background: rgba(215, 222, 80, 0.1);
            border-radius: 50%;
            top: 15%;
            left: 15%;
            animation: float 8s ease-in-out infinite;
        }

        body::after {
            content: '';
            position: absolute;
            width: 100px;
            height: 100px;
            background: rgba(215, 222, 80, 0.08);
            border-radius: 50%;
            bottom: 25%;
            right: 20%;
            animation: float 10s ease-in-out infinite reverse;
        }

        @keyframes float {
            0%, 100% { transform: translateY(0px) rotate(0deg); }
            50% { transform: translateY(-15px) rotate(180deg); }
        }

        .login-container {
            max-width: 450px;
            width: 100%;
            padding: 40px;
            background: var(--white);
            border-radius: 25px;
            box-shadow: 0 25px 50px var(--shadow);
            position: relative;
            backdrop-filter: blur(10px);
            border: 1px solid rgba(255, 255, 255, 0.2);
            animation: slideUp 0.8s ease-out;
        }

        @keyframes slideUp {
            from {
                opacity: 0;
                transform: translateY(30px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .logo {
            text-align: center;
            margin-bottom: 35px;
        }

        .logo-icon {
            width: 70px;
            height: 70px;
            background: linear-gradient(135deg, var(--forest-green), var(--wattle-green));
            border-radius: 18px;
            margin: 0 auto 20px;
            display: flex;
            align-items: center;
            justify-content: center;
            box-shadow: 0 8px 20px rgba(53, 137, 39, 0.3);
            animation: pulse 2s infinite;
        }

        @keyframes pulse {
            0%, 100% { transform: scale(1); }
            50% { transform: scale(1.05); }
        }

        .logo-icon i {
            font-size: 2rem;
            color: var(--white);
        }

        .logo h1 {
            color: var(--forest-green);
            font-size: 2.2rem;
            margin-bottom: 10px;
            font-weight: 700;
            letter-spacing: -1px;
            background: linear-gradient(135deg, var(--forest-green), #2d7a23);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }

        .logo p {
            color: #666;
            font-size: 1rem;
            font-weight: 400;
        }

        .user-type-selector {
            margin-bottom: 25px;
            background: var(--light-gray);
            border-radius: 15px;
            padding: 5px;
            display: flex;
        }

        .user-type-selector .btn-check {
            display: none;
        }

        .user-type-selector .btn {
            flex: 1;
            padding: 12px 20px;
            border: none;
            border-radius: 12px;
            font-weight: 600;
            transition: all 0.3s ease;
            background: transparent;
            color: #666;
            position: relative;
            overflow: hidden;
        }

        .user-type-selector .btn-check:checked + .btn {
            background: linear-gradient(135deg, var(--forest-green), #2d7a23);
            color: var(--white);
            box-shadow: 0 5px 15px rgba(53, 137, 39, 0.3);
            transform: translateY(-2px);
        }

        .user-type-selector .btn:hover:not(.btn-check:checked + .btn) {
            background: rgba(53, 137, 39, 0.1);
            color: var(--forest-green);
        }

        .form-floating {
            margin-bottom: 20px;
            position: relative;
        }

        .form-floating .form-control {
            border: 2px solid #e9ecef;
            border-radius: 12px;
            padding: 16px 20px;
            font-size: 1rem;
            transition: all 0.3s ease;
            background: var(--white);
            height: auto;
        }

        .form-floating .form-control:focus {
            border-color: var(--forest-green);
            box-shadow: 0 0 0 0.2rem rgba(53, 137, 39, 0.15);
            outline: none;
        }

        .form-floating label {
            color: #666;
            font-weight: 500;
            transition: all 0.3s ease;
        }

        .form-floating .form-control:focus ~ label,
        .form-floating .form-control:not(:placeholder-shown) ~ label {
            color: var(--forest-green);
            font-weight: 600;
        }

        .form-control.is-invalid {
            border-color: var(--error-red);
            box-shadow: 0 0 0 0.2rem rgba(220, 53, 69, 0.15);
        }

        .invalid-feedback {
            display: block;
            margin-top: 5px;
            font-size: 0.875rem;
            color: var(--error-red);
        }

        .login-btn {
            width: 100%;
            padding: 16px;
            font-size: 1.1rem;
            font-weight: 600;
            border: none;
            border-radius: 12px;
            background: linear-gradient(135deg, var(--forest-green), #2d7a23);
            color: var(--white);
            transition: all 0.3s ease;
            position: relative;
            overflow: hidden;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            box-shadow: 0 8px 20px rgba(53, 137, 39, 0.3);
        }

        .login-btn::before {
            content: '';
            position: absolute;
            top: 0;
            left: -100%;
            width: 100%;
            height: 100%;
            background: linear-gradient(90deg, transparent, rgba(255,255,255,0.2), transparent);
            transition: left 0.5s;
        }

        .login-btn:hover::before {
            left: 100%;
        }

        .login-btn:hover {
            transform: translateY(-3px);
            box-shadow: 0 12px 30px rgba(53, 137, 39, 0.4);
        }

        .login-btn:active {
            transform: translateY(-1px);
        }

        .alert {
            border-radius: 12px;
            border: none;
            padding: 15px 20px;
            font-weight: 500;
            margin-bottom: 20px;
        }

        .alert-danger {
            background: linear-gradient(135deg, #f8d7da, #f5c6cb);
            color: var(--error-red);
        }

        .alert-success {
            background: linear-gradient(135deg, #d4edda, #c3e6cb);
            color: var(--success-green);
        }

        .login-info {
            margin-top: 25px;
            padding: 20px;
            background: linear-gradient(135deg, rgba(53, 137, 39, 0.05), rgba(215, 222, 80, 0.05));
            border-radius: 15px;
            border-left: 4px solid var(--forest-green);
        }

        .login-info h6 {
            color: var(--forest-green);
            font-weight: 600;
            margin-bottom: 12px;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .login-info ul {
            margin: 0;
            padding-left: 20px;
        }

        .login-info li {
            color: #666;
            margin-bottom: 5px;
            font-size: 0.9rem;
        }

        .login-link {
            text-align: center;
            margin-top: 25px;
        }

        .login-link a {
            color: var(--forest-green);
            text-decoration: none;
            font-weight: 600;
            transition: all 0.3s ease;
        }

        .login-link a:hover {
            color: #2d7a23;
            text-decoration: underline;
        }

        .back-link {
            text-align: center;
            margin-top: 20px;
        }

        .back-link a {
            color: #999;
            text-decoration: none;
            font-size: 0.9rem;
            transition: all 0.3s ease;
        }

        .back-link a:hover {
            color: var(--forest-green);
        }

        .debug-info {
            margin-top: 20px;
            padding: 15px;
            background: #f8f9fa;
            border: 1px solid #dee2e6;
            border-radius: 12px;
            font-family: 'Courier New', monospace;
            font-size: 12px;
            white-space: pre-wrap;
        }

        .debug-info h6 {
            color: var(--error-red);
            margin-bottom: 10px;
            font-family: 'Segoe UI', sans-serif;
        }

        .error-message {
            color: var(--error-red);
            margin-bottom: 1rem;
            text-align: center;
            font-weight: 500;
        }

        /* Responsive design */
        @media (max-width: 768px) {
            .login-container {
                padding: 30px 25px;
                margin: 15px;
            }
            
            .logo h1 {
                font-size: 1.8rem;
            }
            
            .user-type-selector .btn {
                padding: 10px 15px;
                font-size: 0.9rem;
            }
        }

        /* Loading states */
        .loading .login-btn {
            pointer-events: none;
            opacity: 0.7;
        }

        .loading .login-btn::after {
            content: '';
            width: 20px;
            height: 20px;
            border: 2px solid transparent;
            border-top: 2px solid var(--white);
            border-radius: 50%;
            animation: spin 1s linear infinite;
            display: inline-block;
            margin-left: 10px;
        }

        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }
    </style>
</head>
<body>
    <div class="login-container">
        <div class="logo">
            <div class="logo-icon">
                <i class="fas fa-lock"></i>
            </div>
            <h1>Patient Monitoring</h1>
            <p>Please login to continue</p>
        </div>

        <?php 
        if(!empty($login_err)){
            echo '<div class="alert alert-danger"><i class="fas fa-exclamation-triangle me-2"></i>' . $login_err . '</div>';
        }
        if(isset($success_msg)){
            echo '<div class="alert alert-success"><i class="fas fa-check-circle me-2"></i>' . $success_msg . '</div>';
        }
        
        // Display debug information if there were errors
        if($show_debug && !empty($debug_log)):
        ?>
        <div class="debug-info">
            <h6><i class="fas fa-bug"></i> Debug Information:</h6>
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
            echo '<div class="error-message"><i class="fas fa-exclamation-circle me-2"></i>' . htmlspecialchars($error_msg) . '</div>';
        }
        ?>

        <form action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>" method="post" id="loginForm">
            <div class="user-type-selector">
                <input type="radio" class="btn-check" name="user_type" id="doctor" value="doctor" 
                       <?php echo $user_type === 'doctor' ? 'checked' : ''; ?> autocomplete="off">
                <label class="btn" for="doctor">
                    <i class="fas fa-user-md me-2"></i>Doctor
                </label>

                <input type="radio" class="btn-check" name="user_type" id="caretaker" value="caretaker" 
                       <?php echo $user_type === 'caretaker' ? 'checked' : ''; ?> autocomplete="off">
                <label class="btn" for="caretaker">
                    <i class="fas fa-hands-helping me-2"></i>Caretaker
                </label>
            </div>

            <div class="form-floating">
                <input type="text" name="username" class="form-control <?php echo (!empty($username_err)) ? 'is-invalid' : ''; ?>" 
                       value="<?php echo $username; ?>" id="username" placeholder="Username" required>
                <label for="username"><i class="fas fa-user me-2"></i>Username</label>
                <?php if(!empty($username_err)): ?>
                    <div class="invalid-feedback"><?php echo $username_err; ?></div>
                <?php endif; ?>
            </div>    

            <div class="form-floating">
                <input type="password" name="password" class="form-control <?php echo (!empty($password_err)) ? 'is-invalid' : ''; ?>" 
                       id="password" placeholder="Password" required>
                <label for="password"><i class="fas fa-lock me-2"></i>Password</label>
                <?php if(!empty($password_err)): ?>
                    <div class="invalid-feedback"><?php echo $password_err; ?></div>
                <?php endif; ?>
            </div>

            <button class="login-btn" type="submit">
                <i class="fas fa-sign-in-alt me-2"></i>Login
            </button>

            <div class="login-link">
                Don't have an account? <a href="register.php">Register here</a>
            </div>

            <div class="login-info">
                <div id="doctorInfo" <?php echo $user_type === 'caretaker' ? 'style="display:none;"' : ''; ?>>
                    <h6><i class="fas fa-user-md"></i>Doctor Access</h6>
                    <ul>
                        <li>Full dashboard access</li>
                        <li>Patient management</li>
                        <li>Analysis and reports</li>
                    </ul>
                </div>
                <div id="caretakerInfo" <?php echo $user_type === 'doctor' ? 'style="display:none;"' : ''; ?>>
                    <h6><i class="fas fa-hands-helping"></i>Caretaker Access</h6>
                    <ul>
                        <li>View assigned patient only</li>
                        <li>Monitor vital signs</li>
                        <li>View patient details</li>
                    </ul>
                </div>
            </div>
        </form>

        <div class="back-link">
            <a href="home.php">
                <i class="fas fa-arrow-left me-2"></i>Back to Home
            </a>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Toggle access information based on selected user type
            document.querySelectorAll('input[name="user_type"]').forEach(radio => {
                radio.addEventListener('change', function() {
                    document.getElementById('doctorInfo').style.display = this.value === 'doctor' ? 'block' : 'none';
                    document.getElementById('caretakerInfo').style.display = this.value === 'caretaker' ? 'block' : 'none';
                });
            });

            // Form submission with loading state
            const form = document.getElementById('loginForm');
            const loginBtn = document.querySelector('.login-btn');
            
            form.addEventListener('submit', function() {
                loginBtn.disabled = true;
                document.body.classList.add('loading');
                
                // Re-enable button after 10 seconds as failsafe
                setTimeout(() => {
                    loginBtn.disabled = false;
                    document.body.classList.remove('loading');
                }, 10000);
            });

            // Enhanced input focus effects
            const inputs = document.querySelectorAll('.form-control');
            inputs.forEach(input => {
                input.addEventListener('focus', function() {
                    this.parentElement.classList.add('focused');
                });
                
                input.addEventListener('blur', function() {
                    this.parentElement.classList.remove('focused');
                });
            });

            // Add ripple effect to radio buttons
            const radioLabels = document.querySelectorAll('.user-type-selector .btn');
            radioLabels.forEach(label => {
                label.addEventListener('click', function(e) {
                    // Create ripple effect
                    const ripple = document.createElement('span');
                    const rect = this.getBoundingClientRect();
                    const size = Math.max(rect.width, rect.height);
                    const x = e.clientX - rect.left - size / 2;
                    const y = e.clientY - rect.top - size / 2;
                    
                    ripple.style.cssText = `
                        position: absolute;
                        width: ${size}px;
                        height: ${size}px;
                        left: ${x}px;
                        top: ${y}px;
                        background: rgba(255,255,255,0.3);
                        border-radius: 50%;
                        transform: scale(0);
                        animation: ripple 0.6s linear;
                        pointer-events: none;
                    `;
                    
                    this.appendChild(ripple);
                    setTimeout(() => ripple.remove(), 600);
                });
            });
        });

        // Add CSS for ripple animation
        const style = document.createElement('style');
        style.textContent = `
            @keyframes ripple {
                to {
                    transform: scale(4);
                    opacity: 0;
                }
            }
            .user-type-selector .btn {
                position: relative;
                overflow: hidden;
            }
        `;
        document.head.appendChild(style);
    </script>
</body>
</html>