<?php
session_start();
require_once 'db_connection.php';  // Fixed path
// ... rest of the code ...

// Initialize variables
$users_result = null;
$student_count = 0;
$counselor_count = 0;
$total_sessions = 0;

// Get all users with prepared statement
$users_query = $conn->prepare("SELECT r.reg_id, r.username, r.email, ro.role_name as role, r.status, r.created_at 
                             FROM tb_register r
                             JOIN tb_roles ro ON r.role_id = ro.role_id
                             WHERE ro.role_name IN ('student', 'counselor') 
                             ORDER BY ro.role_name, r.created_at DESC");
if ($users_query) {
    $users_query->execute();
    $users_result = $users_query->get_result();
}

// Get student count
$student_query = $conn->prepare("SELECT COUNT(*) as count FROM tb_register r 
                               JOIN tb_roles ro ON r.role_id = ro.role_id 
                               WHERE ro.role_name = ?");
if ($student_query) {
    $role = 'student';
    $student_query->bind_param("s", $role);
    $student_query->execute();
    $result = $student_query->get_result();
    $student_count = $result->fetch_assoc()['count'];
    $student_query->close();
}

// Get counselor count
$counselor_query = $conn->prepare("SELECT COUNT(*) as count FROM tb_register r 
                                 JOIN tb_roles ro ON r.role_id = ro.role_id 
                                 WHERE ro.role_name = ?");
if ($counselor_query) {
    $role = 'counselor';
    $counselor_query->bind_param("s", $role);
    $counselor_query->execute();
    $result = $counselor_query->get_result();
    $counselor_count = $result->fetch_assoc()['count'];
    $counselor_query->close();
}

// Get total sessions
$sessions_query = $conn->prepare("SELECT COUNT(*) as count FROM tb_bookings");
if ($sessions_query) {
    $sessions_query->execute();
    $result = $sessions_query->get_result();
    $total_sessions = $result->fetch_assoc()['count'];
    $sessions_query->close();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard - BrightMind</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.8.1/font/bootstrap-icons.css" rel="stylesheet">
</head>
<body>
    <nav class="navbar navbar-expand-lg navbar-dark bg-dark">
        <div class="container">
            <a class="navbar-brand" href="#">
                <i class="bi bi-shield-lock"></i> BrightMind Admin
            </a>
            <div class="navbar-nav ms-auto">
                <span class="nav-item nav-link text-light">
                    <i class="bi bi-person-circle"></i> <?php echo htmlspecialchars($_SESSION['username']); ?>
                </span>
                <a class="nav-link" href="logout.php">
                    <i class="bi bi-box-arrow-right"></i> Logout
                </a>
            </div>
        </div>
    </nav>

    <div class="container mt-4">
        <div class="row">
            <div class="col-md-3">
                <div class="card">
                    <div class="card-header">
                        <i class="bi bi-list"></i> Quick Links
                    </div>
                    <div class="list-group list-group-flush">
                        <a href="manage_users.php" class="list-group-item list-group-item-action">
                            <i class="bi bi-people"></i> Manage Users
                        </a>
                        <a href="admin/admin_sessions.php" class="list-group-item list-group-item-action">
                            <i class="bi bi-calendar-check"></i> All Sessions
                        </a>
                        <a href="admin/reports.php" class="list-group-item list-group-item-action">
                            <i class="bi bi-graph-up"></i> Reports
                        </a>
                    </div>
                </div>
            </div>

            <div class="col-md-9">
                <h2><i class="bi bi-speedometer2"></i> Admin Dashboard</h2>
                
                <!-- Statistics Cards -->
                <div class="row mt-4">
                    <div class="col-md-4">
                        <div class="card text-white bg-primary mb-3">
                            <div class="card-header">Total Students</div>
                            <div class="card-body">
                                <h5 class="card-title"><?php echo $student_count; ?></h5>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="card text-white bg-success mb-3">
                            <div class="card-header">Total Counselors</div>
                            <div class="card-body">
                                <h5 class="card-title"><?php echo $counselor_count; ?></h5>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="card text-white bg-info mb-3">
                            <div class="card-header">Total Sessions</div>
                            <div class="card-body">
                                <h5 class="card-title"><?php echo $total_sessions; ?></h5>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Users List -->
                <div class="card mt-4">
                    <div class="card-header">
                        <div class="d-flex justify-content-between align-items-center">
                            <h3 class="mb-0">Users Overview</h3>
                            <a href="admin/manage_users.php" class="btn btn-primary btn-sm">
                                <i class="bi bi-plus-circle"></i> Manage Users
                            </a>
                        </div>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-hover">
                                <thead>
                                    <tr>
                                        <th>Username</th>
                                        <th>Email</th>
                                        <th>Role</th>
                                        <th>Status</th>
                                        <th>Joined Date</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if ($users_result && $users_result->num_rows > 0): ?>
                                        <?php while ($user = $users_result->fetch_assoc()): ?>
                                            <tr>
                                                <td><?php echo htmlspecialchars($user['username']); ?></td>
                                                <td><?php echo htmlspecialchars($user['email']); ?></td>
                                                <td>
                                                    <span class="badge bg-<?php echo $user['role'] === 'counselor' ? 'success' : 'primary'; ?>">
                                                        <?php echo ucfirst($user['role']); ?>
                                                    </span>
                                                </td>
                                                <td>
                                                    <span class="badge bg-<?php echo $user['status'] === 'active' ? 'success' : 'warning'; ?>">
                                                        <?php echo ucfirst($user['status']); ?>
                                                    </span>
                                                </td>
                                                <td><?php echo date('M d, Y', strtotime($user['created_at'])); ?></td>
                                                <td>
                                                    <a href="admin/edit_user.php?id=<?php echo $user['reg_id']; ?>" 
                                                       class="btn btn-sm btn-outline-primary">
                                                        <i class="bi bi-pencil"></i>
                                                    </a>
                                                    <button class="btn btn-sm btn-outline-danger" 
                                                            onclick="confirmDelete(<?php echo $user['reg_id']; ?>)">
                                                        <i class="bi bi-trash"></i>
                                                    </button>
                                                </td>
                                            </tr>
                                        <?php endwhile; ?>
                                    <?php else: ?>
                                        <tr>
                                            <td colspan="6" class="text-center">No users found</td>
                                        </tr>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function confirmDelete(userId) {
            if (confirm('Are you sure you want to delete this user?')) {
                window.location.href = `admin/delete_user.php?id=${userId}`;
            }
        }
    </script>
</body>
</html>