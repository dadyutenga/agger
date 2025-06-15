<?php
session_start();
require_once "config.php";

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $username = mysqli_real_escape_string($conn, $_POST['username']);
    $password = $_POST['password'];
    
    $sql = "SELECT id, username, password, full_name FROM doctors WHERE username = ?";
    
    if ($stmt = mysqli_prepare($conn, $sql)) {
        mysqli_stmt_bind_param($stmt, "s", $username);
        
        if (mysqli_stmt_execute($stmt)) {
            mysqli_stmt_store_result($stmt);
            
            if (mysqli_stmt_num_rows($stmt) == 1) {
                mysqli_stmt_bind_result($stmt, $id, $username, $hashed_password, $full_name);
                if (mysqli_stmt_fetch($stmt)) {
                    if (password_verify($password, $hashed_password)) {
                        session_start();
                        
                        $_SESSION["loggedin"] = true;
                        $_SESSION["id"] = $id;
                        $_SESSION["username"] = $username;
                        $_SESSION["full_name"] = $full_name;
                        
                        header("location: dashboard.php");
                    } else {
                        $_SESSION["error"] = "Invalid username or password.";
                        header("location: index.php");
                    }
                }
            } else {
                $_SESSION["error"] = "Invalid username or password.";
                header("location: index.php");
            }
        } else {
            $_SESSION["error"] = "Oops! Something went wrong. Please try again later.";
            header("location: index.php");
        }
        
        mysqli_stmt_close($stmt);
    }
}

mysqli_close($conn);
?> 