<?php
require_once 'db_connection.php';
requireRole('admin');

// Get all sessions with user details
$sessions_query = $conn->prepare("
    SELECT 
        b.booking_id,
        b.booking_date,
        b.booking_time,
        b.duration,
        b.status,
        b.reason,
        s.username as student_name,
        c.username as counselor_name
    FROM tb_bookings b
    JOIN tb_register s ON b.student_id = s.reg_id
    JOIN tb_register c ON b.counselor_id = c.reg_id
    ORDER BY b.booking_date DESC, b.booking_time DESC
");

$sessions_result = null;
if ($sessions_query) {
    $sessions_query->execute();
    $sessions_result = $sessions_query->get_result();
    $sessions_query->close();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>All Sessions - BrightMind Admin</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.8.1/font/bootstrap-icons.css" rel="stylesheet">
</head>
<body>
    <nav class="navbar navbar-expand-lg navbar-dark bg-dark">
        <div class="container">
            <a class="navbar-brand" href="../admin_dashboard.php">
                <i class="bi bi-shield-lock"></i> BrightMind Admin
            </a>
            <div class="navbar-nav ms-auto">
                <a class="nav-link" href="../admin_dashboard.php">
                    <i class="bi bi-speedometer2"></i> Dashboard
                </a>
                <a class="nav-link" href="../logout.php">
                    <i class="bi bi-box-arrow-right"></i> Logout
                </a>
            </div>
        </div>
    </nav>

    <div class="container mt-4">
        <div class="card">
            <div class="card-header">
                <h3 class="mb-0">All Counseling Sessions</h3>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-hover">
                        <thead>
                            <tr>
                                <th>Date</th>
                                <th>Time</th>
                                <th>Student</th>
                                <th>Counselor</th>
                                <th>Duration</th>
                                <th>Reason</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if ($sessions_result && $sessions_result->num_rows > 0): ?>
                                <?php while ($session = $sessions_result->fetch_assoc()): ?>
                                    <tr>
                                        <td><?php echo date('M d, Y', strtotime($session['booking_date'])); ?></td>
                                        <td><?php echo date('h:i A', strtotime($session['booking_time'])); ?></td>
                                        <td><?php echo htmlspecialchars($session['student_name']); ?></td>
                                        <td><?php echo htmlspecialchars($session['counselor_name']); ?></td>
                                        <td><?php echo $session['duration']; ?> minutes</td>
                                        <td>
                                            <?php 
                                            $reason = htmlspecialchars($session['reason']);
                                            echo strlen($reason) > 50 ? substr($reason, 0, 47) . '...' : $reason;
                                            ?>
                                        </td>
                                        <td>
                                            <span class="badge bg-<?php 
                                                echo match($session['status']) {
                                                    'completed' => 'success',
                                                    'pending' => 'warning',
                                                    'cancelled' => 'danger',
                                                    'approved' => 'primary',
                                                    default => 'secondary'
                                                };
                                            ?>">
                                                <?php echo ucfirst($session['status']); ?>
                                            </span>
                                        </td>
                                    </tr>
                                <?php endwhile; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="7" class="text-center">No sessions found</td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>