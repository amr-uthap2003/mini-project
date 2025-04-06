<?php
require_once 'db_connection.php';
requireRole('admin');

$user_details = getUserDetails($conn, $_SESSION['reg_id']);

// Get real-time statistics
function getDashboardStats($conn) {
    $stats = [
        'total_users' => 0,
        'active_sessions' => 0,
        'new_users_today' => 0
    ];
    
    // Get total users
    $result = $conn->query("SELECT COUNT(*) as total FROM tb_register");
    if ($result) {
        $stats['total_users'] = $result->fetch_assoc()['total'];
    }
    
    // Get active sessions
    $result = $conn->query("SELECT COUNT(*) as active FROM tb_login_sessions WHERE status = 'active'");
    if ($result) {
        $stats['active_sessions'] = $result->fetch_assoc()['active'];
    }
    
    // Get new users today
    $result = $conn->query("SELECT COUNT(*) as new_users FROM tb_register WHERE DATE(created_at) = CURDATE()");
    if ($result) {
        $stats['new_users_today'] = $result->fetch_assoc()['new_users'];
    }
    
    return $stats;
}

$stats = getDashboardStats($conn);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard - BrightMind</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.8.1/font/bootstrap-icons.css" rel="stylesheet">
    <style>
        .dashboard-card {
            transition: transform 0.2s;
        }
        .dashboard-card:hover {
            transform: translateY(-5px);
        }
        .nav-link.active {
            background-color: #495057;
            color: white !important;
        }
    </style>
</head>
<body>
    <nav class="navbar navbar-expand-lg navbar-dark bg-dark">
        <div class="container">
            <a class="navbar-brand" href="admin_dashboard.php">
                <i class="bi bi-speedometer2"></i> BrightMind Admin
            </a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarNav">
                <div class="navbar-nav ms-auto">
                    <span class="nav-item nav-link text-light">
                        <i class="bi bi-person-circle"></i> 
                        <?php echo htmlspecialchars($_SESSION['username']); ?>
                    </span>
                    <a class="nav-link" href="logout.php">
                        <i class="bi bi-box-arrow-right"></i> Logout
                    </a>
                </div>
            </div>
        </div>
    </nav>

    <div class="container mt-4">
        <div class="row">
            <div class="col-md-3">
                <div class="card">
                    <div class="card-header bg-dark text-white">
                        <i class="bi bi-list"></i> Quick Links
                    </div>
                    <div class="list-group list-group-flush">
                        <a href="admin/manage_users.php" class="list-group-item list-group-item-action">
                            <i class="bi bi-people"></i> Manage Users
                        </a>
                        <a href="admin/manage_counselors.php" class="list-group-item list-group-item-action">
                            <i class="bi bi-person-badge"></i> Manage Counselors
                        </a>
                        <a href="admin/system_settings.php" class="list-group-item list-group-item-action">
                            <i class="bi bi-gear"></i> System Settings
                        </a>
                        <a href="admin/reports.php" class="list-group-item list-group-item-action">
                            <i class="bi bi-file-text"></i> Reports
                        </a>
                    </div>
                </div>
            </div>
            <div class="col-md-9">
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <h2><i class="bi bi-speedometer2"></i> Dashboard</h2>
                    <button class="btn btn-primary" onclick="refreshStats()">
                        <i class="bi bi-arrow-clockwise"></i> Refresh Stats
                    </button>
                </div>
                <div class="row" id="stats-container">
                    <div class="col-md-4">
                        <div class="card text-white bg-primary mb-3 dashboard-card">
                            <div class="card-header">
                                <i class="bi bi-people-fill"></i> Total Users
                            </div>
                            <div class="card-body">
                                <h5 class="card-title"><?php echo $stats['total_users']; ?></h5>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="card text-white bg-success mb-3 dashboard-card">
                            <div class="card-header">
                                <i class="bi bi-person-check-fill"></i> Active Sessions
                            </div>
                            <div class="card-body">
                                <h5 class="card-title"><?php echo $stats['active_sessions']; ?></h5>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="card text-white bg-info mb-3 dashboard-card">
                            <div class="card-header">
                                <i class="bi bi-person-plus-fill"></i> New Users Today
                            </div>
                            <div class="card-body">
                                <h5 class="card-title"><?php echo $stats['new_users_today']; ?></h5>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function refreshStats() {
            fetch('admin/get_stats.php')
                .then(response => response.json())
                .then(data => {
                    document.querySelector('.bg-primary .card-title').textContent = data.total_users;
                    document.querySelector('.bg-success .card-title').textContent = data.active_sessions;
                    document.querySelector('.bg-info .card-title').textContent = data.new_users_today;
                })
                .catch(error => console.error('Error:', error));
        }

        // Set active nav item
        document.addEventListener('DOMContentLoaded', function() {
            const currentPath = window.location.pathname;
            document.querySelectorAll('.list-group-item').forEach(link => {
                if (link.getAttribute('href') === currentPath) {
                    link.classList.add('active');
                }
            });
        });
    </script>
</body>
</html>