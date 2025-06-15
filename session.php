<?php
// Initialize the session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// List of pages that don't require login
$public_pages = array(
    'index.php',
    'home.php',
    'setup_database.php'
);

// Get the current page name
$current_page = basename($_SERVER['PHP_SELF']);

// Function to check if current page is public
function isPublicPage($page, $public_pages) {
    return in_array($page, $public_pages);
}

// Force login check for non-public pages
if (!isPublicPage($current_page, $public_pages) && !isset($_SESSION["loggedin"])) {
    header("location: index.php?error=login_required");
    exit;
}

// Function to check if user is logged in
function isLoggedIn() {
    return isset($_SESSION["loggedin"]) && $_SESSION["loggedin"] === true;
}

// Function to check if user is a doctor
function isDoctor() {
    return isset($_SESSION["user_type"]) && $_SESSION["user_type"] === "doctor";
}

// Function to check if user is a caretaker
function isCaretaker() {
    return isset($_SESSION["user_type"]) && $_SESSION["user_type"] === "caretaker";
}

// Function to get user's full name
function getUserFullName() {
    return isset($_SESSION["full_name"]) ? $_SESSION["full_name"] : $_SESSION["username"];
}

// Function to get user's ID (either numeric ID for doctors or device_id for caretakers)
function getUserId() {
    if (isDoctor()) {
        return $_SESSION["id"];
    } else {
        return $_SESSION["username"]; // For caretakers, username is their device_id
    }
}

// Check if the session has expired (optional, 30 minutes timeout)
$session_timeout = 1800; // 30 minutes in seconds
if (isset($_SESSION['last_activity']) && (time() - $_SESSION['last_activity'] > $session_timeout)) {
    // Session has expired
    session_unset();     // Unset all session variables
    session_destroy();   // Destroy the session
    header("location: index.php?error=timeout");
    exit;
}

// Update last activity time stamp
$_SESSION['last_activity'] = time();

// Function to validate user access to specific pages
function validatePageAccess($required_type = null) {
    if (!isLoggedIn()) {
        header("location: index.php?error=login_required");
        exit;
    }
    
    if ($required_type !== null) {
        $is_authorized = ($required_type === "doctor" && isDoctor()) || 
                        ($required_type === "caretaker" && isCaretaker());
        
        if (!$is_authorized) {
            header("location: index.php?error=unauthorized");
            exit;
        }
    }
}
?> 