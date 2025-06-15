<?php
$current_page = basename($_SERVER['PHP_SELF']);
$show_back_button = ($current_page === 'patient_details.php');
?>

<nav class="navbar navbar-expand-lg navbar-dark bg-hospital">
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
            <ul class="navbar-nav">
                <li class="nav-item">
                    <a class="nav-link <?php echo $current_page === 'dashboard.php' ? 'active' : ''; ?>" 
                       href="dashboard.php">Dashboard</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link <?php echo $current_page === 'register_patient.php' ? 'active' : ''; ?>" 
                       href="register_patient.php">Register Patient</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link <?php echo $current_page === 'patient_list.php' ? 'active' : ''; ?>" 
                       href="patient_list.php">Patient List</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link <?php echo $current_page === 'analysis.php' ? 'active' : ''; ?>" 
                       href="analysis.php">Analysis</a>
                </li>
            </ul>

            <ul class="navbar-nav ms-auto">
                <li class="nav-item dropdown">
                    <a class="nav-link dropdown-toggle" href="#" id="navbarDropdown" role="button" 
                       data-bs-toggle="dropdown" aria-expanded="false">
                        <i class="bx bx-user"></i> <?php echo htmlspecialchars($_SESSION['full_name']); ?>
                    </a>
                    <ul class="dropdown-menu dropdown-menu-end" aria-labelledby="navbarDropdown">
                        <li><a class="dropdown-item" href="profile.php">Profile</a></li>
                        <li><hr class="dropdown-divider"></li>
                        <li><a class="dropdown-item" href="logout.php">Logout</a></li>
                    </ul>
                </li>
            </ul>
        </div>
    </div>
</nav>

<style>
.bg-hospital {
    background: linear-gradient(135deg, #1a75ff 0%, #0052cc 100%) !important;
}
.navbar {
    padding: 0.75rem 1rem;
    border-bottom: 1px solid rgba(255, 255, 255, 0.1);
    box-shadow: 0 2px 8px rgba(0, 0, 0, 0.15);
}
.navbar-brand {
    font-weight: 600;
    color: white !important;
    font-size: 1.3rem;
    letter-spacing: 0.5px;
}
.navbar-dark .nav-link {
    color: rgba(255, 255, 255, 0.9) !important;
    transition: all 0.3s ease;
    padding: 0.5rem 1rem;
    margin: 0 0.2rem;
    border-radius: 4px;
}
.navbar-dark .nav-link:hover {
    color: white !important;
    background: rgba(255, 255, 255, 0.1);
    transform: translateY(-1px);
}
.navbar-dark .nav-link.active {
    color: white !important;
    font-weight: 600;
    position: relative;
    background: rgba(255, 255, 255, 0.15);
}
.navbar-dark .nav-link.active:after {
    content: '';
    position: absolute;
    bottom: 0;
    left: 1rem;
    right: 1rem;
    height: 2px;
    background: white;
    transform: scaleX(0.8);
    transition: transform 0.3s ease;
}
.navbar-dark .nav-link.active:hover:after {
    transform: scaleX(1);
}
.dropdown-menu {
    border: none;
    box-shadow: 0 4px 15px rgba(0, 0, 0, 0.1);
    margin-top: 0.5rem;
    border-radius: 8px;
    padding: 0.5rem;
}
.dropdown-item {
    padding: 0.5rem 1rem;
    border-radius: 4px;
    transition: all 0.2s ease;
}
.dropdown-item:hover {
    background: #f0f7ff;
    color: #0052cc;
    transform: translateX(3px);
}
.btn-light {
    font-weight: 500;
    border: none;
    box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
    padding: 0.5rem 1.2rem;
    border-radius: 6px;
    transition: all 0.3s ease;
}
.btn-light:hover {
    background: white;
    transform: translateY(-1px);
    box-shadow: 0 4px 8px rgba(0, 0, 0, 0.15);
}
.btn-light i {
    margin-right: 0.3rem;
    transition: transform 0.3s ease;
}
.btn-light:hover i {
    transform: translateX(-2px);
}
.navbar-toggler {
    border: none;
    padding: 0.5rem;
}
.navbar-toggler:focus {
    box-shadow: none;
    outline: 2px solid rgba(255, 255, 255, 0.5);
    outline-offset: 2px;
}
@media (max-width: 991.98px) {
    .navbar-collapse {
        padding: 1rem 0;
    }
    .navbar-nav {
        padding: 0.5rem 0;
    }
    .nav-link {
        padding: 0.5rem 0;
    }
    .navbar-dark .nav-link.active:after {
        display: none;
    }
    .navbar-dark .nav-link.active {
        background: rgba(255, 255, 255, 0.1);
        padding-left: 1rem;
    }
}
</style> 