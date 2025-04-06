<?php
session_start();
require_once 'db_connection.php';

if (!isset($_SESSION['role_name']) || $_SESSION['role_name'] !== 'admin') {
    header("Location: login.php");
    exit();
}

$sessions_query = "SELECT 
    b.*,
    s.username as student_name,
    c.username as counselor_name,
    cl.class_name,
    cl.session_type,
    cl.duration
    FROM tb_bookings b
    JOIN tb_register s ON b.student_id = s.reg_id
    JOIN tb_register c ON b.counselor_id = c.reg_id
    JOIN tb_classes cl ON b.class_id = cl.class_id
    ORDER BY b.booking_date DESC";

$sessions_result = $conn->query($sessions_query);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sessions Overview - Admin</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.datatables.net/1.11.5/css/dataTables.bootstrap5.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css">
    <style>
        .badge.bg-pending { background-color: #ffc107; }
        .badge.bg-active { background-color: #198754; }
        .badge.bg-completed { background-color: #0dcaf0; }
        .badge.bg-cancelled { background-color: #dc3545; }
    </style>
</head>
<body class="bg-light">
    <nav class="navbar navbar-expand-lg navbar-dark bg-primary mb-4">
        <div class="container">
            <a class="navbar-brand" href="#"><i class="fas fa-brain me-2"></i>Admin Panel</a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav me-auto">
                    <li class="nav-item">
                        <a class="nav-link" href="dashboard.php">
                            <i class="fas fa-home me-1"></i>Dashboard
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link active" href="sessions.php">
                            <i class="fas fa-calendar-check me-1"></i>Sessions
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="users.php">
                            <i class="fas fa-users me-1"></i>Users
                        </a>
                    </li>
                </ul>
                <div class="d-flex">
                    <div class="dropdown">
                        <button class="btn btn-light dropdown-toggle" type="button" data-bs-toggle="dropdown">
                            <i class="fas fa-user me-2"></i><?php echo htmlspecialchars($_SESSION['username']); ?>
                        </button>
                        <ul class="dropdown-menu dropdown-menu-end">
                            <li><a class="dropdown-item" href="profile.php"><i class="fas fa-user-circle me-2"></i>Profile</a></li>
                            <li><hr class="dropdown-divider"></li>
                            <li><a class="dropdown-item text-danger" href="logout.php"><i class="fas fa-sign-out-alt me-2"></i>Logout</a></li>
                        </ul>
                    </div>
                </div>
            </div>
        </div>
    </nav>

    <div class="container">
        <div class="card shadow">
            <div class="card-header bg-primary text-white d-flex justify-content-between align-items-center">
                <h5 class="mb-0"><i class="fas fa-list me-2"></i>All Sessions</h5>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-hover" id="sessionsTable">
                        <thead class="table-light">
                            <tr>
                                <th>Class Name</th>
                                <th>Student</th>
                                <th>Counselor</th>
                                <th>Type</th>
                                <th>Date</th>
                                <th>Time</th>
                                <th>Duration</th>
                                <th>Status</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if ($sessions_result && $sessions_result->num_rows > 0): ?>
                                <?php while ($session = $sessions_result->fetch_assoc()): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($session['class_name']); ?></td>
                                        <td><?php echo htmlspecialchars($session['student_name']); ?></td>
                                        <td><?php echo htmlspecialchars($session['counselor_name']); ?></td>
                                        <td>
                                            <i class="fas fa-<?php echo $session['session_type'] === 'video' ? 'video' : ($session['session_type'] === 'text' ? 'comments' : 'person'); ?> me-1"></i>
                                            <?php echo ucfirst($session['session_type']); ?>
                                        </td>
                                        <td><?php echo date('M d, Y', strtotime($session['booking_date'])); ?></td>
                                        <td><?php echo date('h:i A', strtotime($session['booking_time'])); ?></td>
                                        <td><i class="fas fa-clock me-1"></i><?php echo $session['duration']; ?> mins</td>
                                        <td>
                                            <span class="badge bg-<?php echo $session['status']; ?>">
                                                <?php echo ucfirst($session['status']); ?>
                                            </span>
                                        </td>
                                        <td>
                                            <form method="POST" class="d-inline">
                                                <input type="hidden" name="booking_id" value="<?php echo $session['booking_id']; ?>">
                                                <select name="status" class="form-select form-select-sm d-inline-block w-auto me-2">
                                                    <option value="pending" <?php echo $session['status'] === 'pending' ? 'selected' : ''; ?>>Pending</option>
                                                    <option value="active" <?php echo $session['status'] === 'active' ? 'selected' : ''; ?>>Active</option>
                                                    <option value="completed" <?php echo $session['status'] === 'completed' ? 'selected' : ''; ?>>Completed</option>
                                                    <option value="cancelled" <?php echo $session['status'] === 'cancelled' ? 'selected' : ''; ?>>Cancelled</option>
                                                </select>
                                                <button type="submit" name="update_status" class="btn btn-primary btn-sm">
                                                    <i class="fas fa-save me-1"></i>Update
                                                </button>
                                            </form>
                                        </td>
                                    </tr>
                                <?php endwhile; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="9" class="text-center">No sessions found</td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.datatables.net/1.11.5/js/jquery.dataTables.min.js"></script>
    <script src="https://cdn.datatables.net/1.11.5/js/dataTables.bootstrap5.min.js"></script>
    <script>
    $(document).ready(function() {
        $('#sessionsTable').DataTable({
            order: [[4, 'desc']],
            pageLength: 10,
            language: {
                search: "Search sessions:"
            }
        });

        $('form').on('submit', function(e) {
            e.preventDefault();
            const form = $(this);
            const formData = new FormData(form[0]);
            formData.append('ajax_request', '1');

            $.ajax({
                type: 'POST',
                url: window.location.href,
                data: formData,
                processData: false,
                contentType: false,
                success: function(response) {
                    if (response.success) {
                        window.location.reload();
                    } else {
                        alert('Update failed. Please try again.');
                    }
                },
                error: function() {
                    alert('An error occurred. Please try again.');
                }
            });
        });
    });
    </script>
</body>
</html>