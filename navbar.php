<!-- Add Bootstrap and other required dependencies -->
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/boxicons@2.0.7/css/boxicons.min.css" rel="stylesheet">
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>

<?php
require_once "config.php";  // Add database configuration
$current_page = basename($_SERVER['PHP_SELF']);
$show_back_button = ($current_page === 'patient_details.php');
?>

<nav class="navbar navbar-expand-lg navbar-dark bg-primary">
    <div class="container-fluid">
        <?php if ($show_back_button): ?>
            <a href="dashboard.php" class="btn btn-light me-3">
                <i class="bx bx-arrow-back"></i> Back to Dashboard
            </a>
        <?php else: ?>
            <a class="navbar-brand" href="dashboard.php">Patient Monitor</a>
        <?php endif; ?>

        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
            <span class="navbar-toggler-icon"></span>
        </button>

        <div class="collapse navbar-collapse" id="navbarNav">
            <ul class="navbar-nav me-auto">
                <?php if ($_SESSION["user_type"] === "doctor"): ?>
                    <li class="nav-item">
                        <a class="nav-link <?php echo $current_page === 'dashboard.php' ? 'active' : ''; ?>" 
                           href="dashboard.php">Dashboard</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link <?php echo $current_page === 'register_patient.php' ? 'active' : ''; ?>" 
                           href="register_patient.php">Register Patient</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link <?php echo $current_page === 'analysis.php' ? 'active' : ''; ?>" 
                           href="analysis.php">Analysis</a>
                    </li>
                <?php endif; ?>
                
                <!-- Chat link for both doctors and caretakers -->
                <li class="nav-item">
                    <a class="nav-link <?php echo $current_page === 'chat.php' ? 'active' : ''; ?>" 
                       href="<?php echo isset($_SESSION['chat_url']) ? $_SESSION['chat_url'] : 'chat.php'; ?>">
                        <i class="bx bx-message-square-dots"></i> Chat
                        <?php
                        // Show unread message count
                        if(isset($conn)) {  // Check if database connection exists
                            $sql = "SELECT COUNT(*) as unread FROM messages 
                                   WHERE receiver_type = ? 
                                   AND receiver_id = ? 
                                   AND is_read = 0";
                            $stmt = mysqli_prepare($conn, $sql);
                            if($stmt) {
                                $receiver_id = $_SESSION["user_type"] === "doctor" ? $_SESSION["id"] : $_SESSION["username"];
                                mysqli_stmt_bind_param($stmt, "ss", $_SESSION["user_type"], $receiver_id);
                                mysqli_stmt_execute($stmt);
                                $result = mysqli_stmt_get_result($stmt);
                                $row = mysqli_fetch_assoc($result);
                                if($row && $row['unread'] > 0) {
                                    echo '<span class="badge bg-danger ms-1">' . $row['unread'] . '</span>';
                                }
                            }
                        }
                        ?>
                    </a>
                </li>
            </ul>

            <ul class="navbar-nav">
                <li class="nav-item">
                    <span class="nav-link">Welcome, <?php echo htmlspecialchars($_SESSION["full_name"]); ?> 
                        (<?php echo ucfirst($_SESSION["user_type"]); ?>)</span>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="logout.php">Logout</a>
                </li>
            </ul>
        </div>
    </div>
</nav>

<style>
:root {
    --hospital-blue: #0055a4;
    --hospital-blue-light: #0066cc;
}

.navbar {
    padding: 0.5rem 1rem;
    background-color: var(--hospital-blue) !important;
    border-bottom: 1px solid rgba(255,255,255,0.1);
}
.navbar-brand {
    font-weight: 600;
    color: white !important;
}
.nav-link {
    color: rgba(255,255,255,0.9) !important;
    padding: 0.5rem 1rem !important;
    transition: all 0.3s ease;
}
.nav-link:hover {
    color: white !important;
    background: var(--hospital-blue-light);
}
.nav-link.active {
    color: white !important;
    font-weight: 600;
    background: var(--hospital-blue-light);
}
.dropdown-menu {
    border: none;
    box-shadow: 0 2px 10px rgba(0,0,0,0.1);
}
.btn-light {
    background: rgba(255,255,255,0.1);
    border-color: rgba(255,255,255,0.2);
    color: white;
}
.btn-light:hover {
    background: rgba(255,255,255,0.2);
    border-color: rgba(255,255,255,0.3);
    color: white;
}
.navbar-toggler {
    border-color: rgba(255,255,255,0.3);
}
</style> 