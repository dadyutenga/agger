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
    
    // Validate required fields
    $required_fields = ['full_name', 'age', 'gender', 'region', 'ward', 'street', 'caretaker_name', 'caretaker_phone', 'registration_date'];
    foreach($required_fields as $field) {
        if(empty($_POST[$field])) {
            $errors[] = ucfirst(str_replace('_', ' ', $field)) . " is required";
        }
    }

    // Handle image upload
    if(!isset($_FILES["patient_image"]) || $_FILES["patient_image"]["error"] != 0) {
        $errors[] = "Patient photo is required";
    } else {
        $target_dir = "uploads/patients/";
        if (!file_exists($target_dir)) {
            if(!mkdir($target_dir, 0777, true)) {
                $errors[] = "Failed to create upload directory";
            }
        }
        
        $imageFileType = strtolower(pathinfo($_FILES["patient_image"]["name"], PATHINFO_EXTENSION));
        $target_file = $target_dir . time() . "." . $imageFileType;
        
        // Check if image file is actual image
        $check = getimagesize($_FILES["patient_image"]["tmp_name"]);
        if($check === false) {
            $errors[] = "File is not an image";
        }
        
        // Check file size (max 5MB)
        if ($_FILES["patient_image"]["size"] > 5000000) {
            $errors[] = "File is too large (max 5MB)";
        }
        
        // Allow certain file formats
        if($imageFileType != "jpg" && $imageFileType != "png" && $imageFileType != "jpeg") {
            $errors[] = "Only JPG, JPEG & PNG files are allowed";
        }
    }

    // If no errors, proceed with registration
    if(empty($errors)) {
        if(move_uploaded_file($_FILES["patient_image"]["tmp_name"], $target_file)) {
            // Combine address fields
            $full_address = $_POST['region'] . ', ' . $_POST['ward'] . ', ' . $_POST['street'];
            
            $sql = "INSERT INTO patients (
                full_name, age, gender, address, 
                emergency_contact, emergency_phone, doctor_id, 
                device_id, registration_date
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)";
            
            if($stmt = mysqli_prepare($conn, $sql)) {
                $device_id = "ESP32_" . time();
                mysqli_stmt_bind_param($stmt, "sissssiss", 
                    $_POST['full_name'],
                    $_POST['age'],
                    $_POST['gender'],
                    $full_address,
                    $_POST['caretaker_name'],
                    $_POST['caretaker_phone'],
                    $_SESSION['id'],
                    $device_id,
                    $_POST['registration_date']
                );
                
                if(mysqli_stmt_execute($stmt)) {
                    // Get the newly inserted patient ID
                    $patient_id = mysqli_insert_id($conn);
                    
                    // Create caretaker credentials automatically
                    $caretaker_username = "care_" . $device_id;
                    $caretaker_password = "care123"; // Default password
                    $hashed_password = password_hash($caretaker_password, PASSWORD_DEFAULT);
                    
                    // Extract first and last name from caretaker name
                    $name_parts = explode(' ', $_POST['caretaker_name'], 2);
                    $first_name = $name_parts[0];
                    $last_name = isset($name_parts[1]) ? $name_parts[1] : '';
                    
                    // Insert into caretaker_credentials
                    $sql2 = "INSERT INTO caretaker_credentials (first_name, last_name, username, password, patient_id) VALUES (?, ?, ?, ?, ?)";
                    if($stmt2 = mysqli_prepare($conn, $sql2)) {
                        mysqli_stmt_bind_param($stmt2, "ssssi", $first_name, $last_name, $caretaker_username, $hashed_password, $patient_id);
                        if(mysqli_stmt_execute($stmt2)) {
                            $_SESSION["success"] = "Patient " . htmlspecialchars($_POST['full_name']) . " has been registered successfully!<br>" .
                                                 "Device ID: " . $device_id . "<br>" .
                                                 "Image saved to: " . $target_file . "<br>" .
                                                 "Caretaker Login:<br>" .
                                                 "Username: " . $caretaker_username . "<br>" .
                                                 "Password: " . $caretaker_password;
                        } else {
                            $errors[] = "Failed to create caretaker credentials: " . mysqli_error($conn);
                        }
                        mysqli_stmt_close($stmt2);
                    }
                    
                    header("location: register_patient.php");
                    exit();
                } else {
                    $errors[] = "Database Error: " . mysqli_error($conn);
                }
                mysqli_stmt_close($stmt);
            } else {
                $errors[] = "Database Error: " . mysqli_error($conn);
            }
        } else {
            $errors[] = "Failed to upload image file";
        }
    }
    
    // If there were errors, store them in session
    if(!empty($errors)) {
        $_SESSION["error"] = "<ul><li>" . implode("</li><li>", $errors) . "</li></ul>";
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
    <title>Register Patient - Patient Monitoring System</title>
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

        /* Override Bootstrap navbar colors completely */
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

        .navbar-nav .nav-item .nav-link:hover,
        .navbar-nav .nav-item .nav-link:focus,
        .navbar-nav .nav-item .nav-link:active,
        .navbar-nav .nav-item .nav-link:visited {
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

        .navbar-toggler-icon {
            background-image: url("data:image/svg+xml,%3csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 30 30'%3e%3cpath stroke='rgba%28255, 255, 255, 0.85%29' stroke-linecap='round' stroke-miterlimit='10' stroke-width='2' d='M4 7h22M4 15h22M4 23h22'/%3e%3c/svg%3e");
        }

        /* Remove Bootstrap's default link colors */
        a, a:hover, a:focus, a:active, a:visited {
            color: inherit !important;
            text-decoration: none !important;
        }

        .navbar a, .navbar a:hover, .navbar a:focus, .navbar a:active, .navbar a:visited {
            color: var(--white) !important;
        }

        .navbar a:hover, .navbar a:focus {
            color: var(--white) !important;
            background-color: transparent !important;
        }

        /* Remove Bootstrap focus effects */
        .navbar-nav .nav-link:focus,
        .navbar-brand:focus,
        .navbar-toggler:focus,
        *:focus {
            box-shadow: none !important;
            outline: none !important;
            border-color: transparent !important;
        }

        .form-container {
            max-width: 900px;
            margin: 30px auto;
            padding: 30px;
            background: var(--white);
            border-radius: 20px;
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

        .form-label {
            color: var(--forest-green);
            font-weight: 600;
            margin-bottom: 8px;
            font-size: 0.9rem;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .form-control {
            border: 2px solid rgba(53, 137, 39, 0.1);
            border-radius: 10px;
            padding: 12px 15px;
            font-size: 14px;
            transition: all 0.3s ease;
            background: var(--white);
        }

        .form-control:focus {
            border-color: var(--forest-green);
            box-shadow: 0 0 0 0.2rem rgba(53, 137, 39, 0.25);
            outline: none;
        }

        .form-control:hover {
            border-color: var(--wattle-green);
        }

        .btn-primary {
            background: linear-gradient(135deg, var(--forest-green), #2d7a23);
            border: none;
            border-radius: 15px;
            padding: 12px 30px;
            font-weight: 600;
            font-size: 1rem;
            text-transform: uppercase;
            letter-spacing: 1px;
            transition: all 0.3s ease;
            box-shadow: 0 5px 15px var(--shadow);
        }

        .btn-primary:hover {
            background: linear-gradient(135deg, #2d7a23, var(--forest-green));
            transform: translateY(-2px);
            box-shadow: 0 8px 20px rgba(53, 137, 39, 0.3);
        }

        .btn-primary:focus {
            box-shadow: 0 0 0 0.2rem rgba(53, 137, 39, 0.25);
        }

        .alert {
            border-radius: 15px;
            border: none;
            padding: 20px;
            margin-bottom: 25px;
            font-weight: 500;
        }

        .alert-danger {
            background: linear-gradient(135deg, #dc3545, #c82333);
            color: white;
        }

        .alert-success {
            background: linear-gradient(135deg, var(--forest-green), #2d7a23);
            color: white;
        }

        .alert ul {
            margin-bottom: 0;
            padding-left: 20px;
        }

        .preview-image {
            max-width: 200px;
            margin-top: 15px;
            border-radius: 10px;
            border: 3px solid var(--wattle-green);
            box-shadow: 0 4px 10px var(--shadow);
        }

        .card {
            border-radius: 15px;
            border: 2px solid rgba(53, 137, 39, 0.1);
            box-shadow: 0 5px 15px var(--shadow);
            margin-bottom: 25px;
        }

        .card-header {
            background: linear-gradient(135deg, rgba(53, 137, 39, 0.05), rgba(215, 222, 80, 0.05));
            border-radius: 15px 15px 0 0;
            border-bottom: 2px solid rgba(53, 137, 39, 0.1);
            padding: 20px;
        }

        .card-header h5 {
            color: var(--forest-green);
            font-weight: 600;
            margin: 0;
            font-size: 1.1rem;
        }

        .card-body {
            padding: 20px;
        }

        .text-info {
            color: var(--forest-green) !important;
        }

        .fas {
            color: var(--forest-green);
            margin-right: 8px;
        }

        /* Form sections */
        .mb-3 {
            margin-bottom: 25px !important;
        }

        /* Responsive design */
        @media (max-width: 768px) {
            .form-container {
                margin: 20px 10px;
                padding: 20px;
                border-radius: 15px;
            }
            
            h2 {
                font-size: 1.8rem;
            }
            
            .btn-primary {
                width: 100%;
                padding: 15px;
            }
        }

        /* Input file styling */
        input[type="file"] {
            cursor: pointer;
        }

        input[type="file"]::-webkit-file-upload-button {
            background: linear-gradient(135deg, var(--forest-green), #2d7a23);
            color: white;
            border: none;
            border-radius: 8px;
            padding: 8px 15px;
            margin-right: 10px;
            cursor: pointer;
            font-weight: 500;
        }

        input[type="file"]::-webkit-file-upload-button:hover {
            background: linear-gradient(135deg, #2d7a23, var(--forest-green));
        }

        /* Select dropdown styling */
        select.form-control {
            cursor: pointer;
        }

        /* Number input styling */
        input[type="number"] {
            -moz-appearance: textfield;
        }

        input[type="number"]::-webkit-outer-spin-button,
        input[type="number"]::-webkit-inner-spin-button {
            -webkit-appearance: none;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="form-container">
            <h2><i class="fas fa-user-plus me-3"></i>Register New Patient</h2>
            
            <?php 
            if(isset($_SESSION["error"])) { 
                echo '<div class="alert alert-danger"><i class="fas fa-exclamation-triangle me-2"></i>' . $_SESSION["error"] . '</div>';
                unset($_SESSION["error"]);
            }
            if(isset($_SESSION["success"])) { 
                echo '<div class="alert alert-success"><i class="fas fa-check-circle me-2"></i>' . $_SESSION["success"] . '</div>';
                unset($_SESSION["success"]);
            }
            ?>

            <form action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>" method="post" enctype="multipart/form-data" id="registrationForm">
                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label class="form-label"><i class="fas fa-user me-2"></i>Full Name</label>
                        <input type="text" name="full_name" class="form-control" required value="<?php echo isset($_POST['full_name']) ? htmlspecialchars($_POST['full_name']) : ''; ?>" placeholder="Enter patient's full name">
                    </div>
                    <div class="col-md-3 mb-3">
                        <label class="form-label"><i class="fas fa-birthday-cake me-2"></i>Age</label>
                        <input type="number" name="age" class="form-control" required min="0" max="150" value="<?php echo isset($_POST['age']) ? htmlspecialchars($_POST['age']) : ''; ?>" placeholder="Age">
                    </div>
                    <div class="col-md-3 mb-3">
                        <label class="form-label"><i class="fas fa-venus-mars me-2"></i>Gender</label>
                        <select name="gender" class="form-control" required>
                            <option value="">Select Gender</option>
                            <option value="Male" <?php echo (isset($_POST['gender']) && $_POST['gender'] == 'Male') ? 'selected' : ''; ?>>Male</option>
                            <option value="Female" <?php echo (isset($_POST['gender']) && $_POST['gender'] == 'Female') ? 'selected' : ''; ?>>Female</option>
                            <option value="Other" <?php echo (isset($_POST['gender']) && $_POST['gender'] == 'Other') ? 'selected' : ''; ?>>Other</option>
                        </select>
                    </div>
                </div>

                <div class="row">
                    <div class="col-md-4 mb-3">
                        <label class="form-label"><i class="fas fa-globe me-2"></i>Region</label>
                        <input type="text" name="region" class="form-control" required value="<?php echo isset($_POST['region']) ? htmlspecialchars($_POST['region']) : ''; ?>" placeholder="Enter region">
                    </div>
                    <div class="col-md-4 mb-3">
                        <label class="form-label"><i class="fas fa-map-marker-alt me-2"></i>Ward</label>
                        <input type="text" name="ward" class="form-control" required value="<?php echo isset($_POST['ward']) ? htmlspecialchars($_POST['ward']) : ''; ?>" placeholder="Enter ward">
                    </div>
                    <div class="col-md-4 mb-3">
                        <label class="form-label"><i class="fas fa-road me-2"></i>Street</label>
                        <input type="text" name="street" class="form-control" required value="<?php echo isset($_POST['street']) ? htmlspecialchars($_POST['street']) : ''; ?>" placeholder="Enter street">
                    </div>
                </div>

                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label class="form-label"><i class="fas fa-user-nurse me-2"></i>Caretaker Name</label>
                        <input type="text" name="caretaker_name" class="form-control" required value="<?php echo isset($_POST['caretaker_name']) ? htmlspecialchars($_POST['caretaker_name']) : ''; ?>" placeholder="Enter caretaker's name">
                    </div>
                    <div class="col-md-6 mb-3">
                        <label class="form-label"><i class="fas fa-phone me-2"></i>Caretaker Phone</label>
                        <input type="tel" name="caretaker_phone" class="form-control" required pattern="[0-9]+" value="<?php echo isset($_POST['caretaker_phone']) ? htmlspecialchars($_POST['caretaker_phone']) : ''; ?>" placeholder="Enter phone number">
                    </div>
                </div>

                <div class="card mb-4">
                    <div class="card-header">
                        <h5><i class="fas fa-key me-2"></i>Caretaker Access</h5>
                    </div>
                    <div class="card-body">
                        <p class="text-info mb-0">
                            <i class="fas fa-info-circle me-2"></i>
                            Caretaker login credentials will be generated automatically upon registration.
                            The credentials will be displayed after successful registration.
                        </p>
                    </div>
                </div>

                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label class="form-label"><i class="fas fa-calendar me-2"></i>Registration Date</label>
                        <input type="date" name="registration_date" class="form-control" required value="<?php echo isset($_POST['registration_date']) ? htmlspecialchars($_POST['registration_date']) : date('Y-m-d'); ?>">
                    </div>
                    <div class="col-md-6 mb-3">
                        <label class="form-label"><i class="fas fa-camera me-2"></i>Patient Photo</label>
                        <input type="file" name="patient_image" class="form-control" accept="image/jpeg,image/png,image/jpg" required onchange="previewImage(this)">
                        <img id="preview" class="preview-image d-none">
                    </div>
                </div>

                <div class="text-center">
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-save me-2"></i>Register Patient
                    </button>
                </div>
            </form>
        </div>
    </div>

    <script>
        function previewImage(input) {
            if (input.files && input.files[0]) {
                var reader = new FileReader();
                reader.onload = function(e) {
                    document.getElementById('preview').src = e.target.result;
                    document.getElementById('preview').classList.remove('d-none');
                }
                reader.readAsDataURL(input.files[0]);
            }
        }

        // Form validation
        document.getElementById('registrationForm').addEventListener('submit', function(e) {
            var phone = document.querySelector('input[name="caretaker_phone"]');
            if(!/^[0-9]+$/.test(phone.value)) {
                e.preventDefault();
                alert('Phone number should contain only digits');
                phone.focus();
            }
        });
    </script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>