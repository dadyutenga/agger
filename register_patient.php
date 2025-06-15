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
            $sql = "INSERT INTO patients (
                full_name, age, gender, region, ward, street, 
                emergency_contact, emergency_phone, doctor_id, 
                device_id, registration_date, image_path, status
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'Active')";
            
            if($stmt = mysqli_prepare($conn, $sql)) {
                $device_id = "ESP32_" . time();
                mysqli_stmt_bind_param($stmt, "sissssssisss", 
                    $_POST['full_name'],
                    $_POST['age'],
                    $_POST['gender'],
                    $_POST['region'],
                    $_POST['ward'],
                    $_POST['street'],
                    $_POST['caretaker_name'],
                    $_POST['caretaker_phone'],
                    $_SESSION['id'],
                    $device_id,
                    $_POST['registration_date'],
                    $target_file
                );
                
                if(mysqli_stmt_execute($stmt)) {
                    // Get the newly inserted patient ID
                    $patient_id = mysqli_insert_id($conn);
                    
                    // Create caretaker credentials automatically
                    $caretaker_username = "care_" . $device_id;
                    $caretaker_password = "care123"; // Default password
                    $hashed_password = password_hash($caretaker_password, PASSWORD_DEFAULT);
                    
                    // Insert into caretaker_credentials
                    $sql = "INSERT INTO caretaker_credentials (patient_id, username, password) VALUES (?, ?, ?)";
                    if($stmt2 = mysqli_prepare($conn, $sql)) {
                        mysqli_stmt_bind_param($stmt2, "iss", $patient_id, $caretaker_username, $hashed_password);
                        if(mysqli_stmt_execute($stmt2)) {
                            $_SESSION["success"] = "Patient " . htmlspecialchars($_POST['full_name']) . " has been registered successfully!<br>" .
                                                 "Device ID: " . $device_id . "<br>" .
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
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Register Patient - Patient Monitoring System</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        .form-container {
            max-width: 800px;
            margin: 30px auto;
            padding: 20px;
            background: white;
            border-radius: 10px;
            box-shadow: 0 0 10px rgba(0,0,0,0.1);
        }
        .preview-image {
            max-width: 200px;
            margin-top: 10px;
        }
        .alert {
            margin-top: 20px;
        }
        .alert ul {
            margin-bottom: 0;
            padding-left: 20px;
        }
    </style>
</head>
<body class="bg-light">
    <?php include 'navbar.php'; ?>

    <div class="container">
        <div class="form-container">
            <h2 class="mb-4">Register New Patient</h2>
            
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

            <form action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>" method="post" enctype="multipart/form-data" id="registrationForm">
                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label class="form-label">Full Name</label>
                        <input type="text" name="full_name" class="form-control" required value="<?php echo isset($_POST['full_name']) ? htmlspecialchars($_POST['full_name']) : ''; ?>">
                    </div>
                    <div class="col-md-3 mb-3">
                        <label class="form-label">Age</label>
                        <input type="number" name="age" class="form-control" required min="0" max="150" value="<?php echo isset($_POST['age']) ? htmlspecialchars($_POST['age']) : ''; ?>">
                    </div>
                    <div class="col-md-3 mb-3">
                        <label class="form-label">Gender</label>
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
                        <label class="form-label">Region</label>
                        <input type="text" name="region" class="form-control" required value="<?php echo isset($_POST['region']) ? htmlspecialchars($_POST['region']) : ''; ?>">
                    </div>
                    <div class="col-md-4 mb-3">
                        <label class="form-label">Ward</label>
                        <input type="text" name="ward" class="form-control" required value="<?php echo isset($_POST['ward']) ? htmlspecialchars($_POST['ward']) : ''; ?>">
                    </div>
                    <div class="col-md-4 mb-3">
                        <label class="form-label">Street</label>
                        <input type="text" name="street" class="form-control" required value="<?php echo isset($_POST['street']) ? htmlspecialchars($_POST['street']) : ''; ?>">
                    </div>
                </div>

                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label class="form-label">Caretaker Name</label>
                        <input type="text" name="caretaker_name" class="form-control" required value="<?php echo isset($_POST['caretaker_name']) ? htmlspecialchars($_POST['caretaker_name']) : ''; ?>">
                    </div>
                    <div class="col-md-6 mb-3">
                        <label class="form-label">Caretaker Phone</label>
                        <input type="tel" name="caretaker_phone" class="form-control" required pattern="[0-9]+" value="<?php echo isset($_POST['caretaker_phone']) ? htmlspecialchars($_POST['caretaker_phone']) : ''; ?>">
                    </div>
                </div>

                <div class="card mb-4">
                    <div class="card-header">
                        <h5 class="mb-0">Caretaker Access</h5>
                    </div>
                    <div class="card-body">
                        <p class="text-info mb-0">
                            <i class="fas fa-info-circle"></i>
                            Caretaker login credentials will be generated automatically upon registration.
                            The credentials will be displayed after successful registration.
                        </p>
                    </div>
                </div>

                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label class="form-label">Registration Date</label>
                        <input type="date" name="registration_date" class="form-control" required value="<?php echo isset($_POST['registration_date']) ? htmlspecialchars($_POST['registration_date']) : date('Y-m-d'); ?>">
                    </div>
                    <div class="col-md-6 mb-3">
                        <label class="form-label">Patient Photo</label>
                        <input type="file" name="patient_image" class="form-control" accept="image/jpeg,image/png" required onchange="previewImage(this)">
                        <img id="preview" class="preview-image d-none">
                    </div>
                </div>

                <button type="submit" class="btn btn-primary">Register Patient</button>
            </form>
        </div>
    </div>

    <script>
        function previewImage(input) {
            if (input.files && input.files[0]) {
                var reader = new FileReader();
                reader.onload = function(e) {
                    $('#preview').attr('src', e.target.result).removeClass('d-none');
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
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html> 