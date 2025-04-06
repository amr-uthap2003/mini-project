<?php
session_start();
require_once 'db_connection.php';

if (!isset($_SESSION['role_name']) || $_SESSION['role_name'] !== 'counselor') {
    header("Location: login.php");
    exit();
}

$counselor_id = $_SESSION['reg_id'];

try {
    // Fetch the count of students with booked sessions with this counselor
    $students_query = "SELECT COUNT(DISTINCT b.student_id) as total_students 
                      FROM tb_bookings b
                      WHERE b.counselor_id = ?";
    $stmt = $conn->prepare($students_query);
    if (!$stmt) {
        throw new Exception($conn->error);
    }
    $stmt->bind_param("i", $counselor_id);
    $stmt->execute();
    $students_result = $stmt->get_result();
    $students_count = $students_result->fetch_assoc()['total_students'];

    // Fetch today's sessions
    $today = date('Y-m-d');
    $today_sessions_query = "SELECT COUNT(*) as today_sessions 
                            FROM tb_bookings 
                            WHERE counselor_id = ? 
                            AND booking_date = ? 
                            AND status = 'approved'";
    $stmt = $conn->prepare($today_sessions_query);
    if (!$stmt) {
        throw new Exception($conn->error);
    }
    $stmt->bind_param("is", $counselor_id, $today);
    $stmt->execute();
    $today_result = $stmt->get_result();
    $today_sessions = $today_result->fetch_assoc()['today_sessions'];

    // Fetch pending requests
    $pending_query = "SELECT COUNT(*) as pending_requests 
                    FROM tb_bookings 
                    WHERE counselor_id = ? 
                    AND status = 'pending'";
    $stmt = $conn->prepare($pending_query);
    if (!$stmt) {
        throw new Exception($conn->error);
    }
    $stmt->bind_param("i", $counselor_id);
    $stmt->execute();
    $pending_result = $stmt->get_result();
    $pending_requests = $pending_result->fetch_assoc()['pending_requests'];

    // Get upcoming sessions (next 5 days)
    $upcoming_query = "SELECT b.*, r.fullname as student_name, r.education as grade 
                      FROM tb_bookings b
                      JOIN tb_register r ON b.student_id = r.reg_id
                      WHERE b.counselor_id = ? 
                      AND b.status = 'approved'
                      AND b.booking_date BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 5 DAY)
                      ORDER BY b.booking_date, b.booking_time
                      LIMIT 5";
    $stmt = $conn->prepare($upcoming_query);
    if (!$stmt) {
        throw new Exception($conn->error);
    }
    $stmt->bind_param("i", $counselor_id);
    $stmt->execute();
    $upcoming_result = $stmt->get_result();

    // Fetch availability status
    $availability_query = "SELECT COUNT(*) as has_availability 
                          FROM tb_counselor_availability 
                          WHERE counselor_id = ?";
    $stmt = $conn->prepare($availability_query);
    if (!$stmt) {
        throw new Exception($conn->error);
    }
    $stmt->bind_param("i", $counselor_id);
    $stmt->execute();
    $availability_result = $stmt->get_result();
    $has_availability = $availability_result->fetch_assoc()['has_availability'] > 0;

    // Fetch class distribution
    $class_query = "SELECT r.education as grade, COUNT(*) as student_count 
                    FROM tb_bookings b
                    JOIN tb_register r ON b.student_id = r.reg_id
                    WHERE b.counselor_id = ?
                    GROUP BY r.education
                    ORDER BY r.education";
    $stmt = $conn->prepare($class_query);
    if (!$stmt) {
        throw new Exception($conn->error);
    }
    $stmt->bind_param("i", $counselor_id);
    $stmt->execute();
    $class_result = $stmt->get_result();

} catch (Exception $e) {
    error_log("Database error: " . $e->getMessage());
    $students_count = 0;
    $today_sessions = 0;
    $pending_requests = 0;
    $upcoming_result = null;
    $has_availability = false;
    $class_result = null;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Counselor Dashboard - BrightMind</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.8.1/font/bootstrap-icons.css" rel="stylesheet">
    <style>
        .card {
            box-shadow: 0 0.125rem 0.25rem rgba(0, 0, 0, 0.075);
            transition: transform 0.2s;
        }
        .card:hover {
            transform: translateY(-2px);
        }
        .list-group-item {
            transition: background-color 0.2s;
        }
        .list-group-item:hover {
            background-color: #f8f9fa;
        }
        .table-hover tbody tr:hover {
            background-color: rgba(0,0,0,.02);
        }
        .progress {
            height: 20px;
        }
        .progress-bar {
            transition: width 0.6s ease;
        }
    </style>
</head>
<body class="bg-light">
    <nav class="navbar navbar-expand-lg navbar-dark bg-primary">
        <div class="container">
            <a class="navbar-brand" href="#">
                <i class="bi bi-brightness-high"></i> BrightMind Counselor
            </a>
            <div class="navbar-nav ms-auto">
                <span class="nav-item nav-link text-light">
                    <i class="bi bi-person-circle"></i> 
                    Welcome, <?php echo htmlspecialchars($_SESSION['username']); ?>
                </span>
                <a class="nav-link" href="logout.php">
                    <i class="bi bi-box-arrow-right"></i> Logout
                </a>
            </div>
        </div>
    </nav>

    <div class="container mt-4">
        <?php if (!$has_availability): ?>
        <div class="alert alert-warning" role="alert">
            <i class="bi bi-exclamation-triangle"></i>
            You haven't set your availability yet. 
            <a href="counselor_availability.php" class="alert-link">Click here to set your available times</a>.
        </div>
        <?php endif; ?>

        <div class="row">
            <div class="col-md-3">
                <div class="card mb-4">
                    <div class="card-header bg-primary text-white">
                        <i class="bi bi-list"></i> Quick Links
                    </div>
                    <div class="list-group list-group-flush">
                        <a href="student_list.php" class="list-group-item list-group-item-action">
                            <i class="bi bi-people"></i> Student List
                        </a>
                        <a href="counselor_availability.php" class="list-group-item list-group-item-action">
                            <i class="bi bi-clock"></i> Manage Availability
                        </a>
                        <a href="pending_sessions.php" class="list-group-item list-group-item-action">
                            <i class="bi bi-calendar-check"></i> Pending Sessions
                        </a>
                        <a href="class_management.php" class="list-group-item list-group-item-action">
                            <i class="bi bi-mortarboard"></i> Class Management
                        </a>
                        <a href="counselor_profile.php" class="list-group-item list-group-item-action">
                            <i class="bi bi-person"></i> My Profile
                        </a>
                    </div>
                </div>
            </div>
            <div class="col-md-9">
                <h2 class="mb-4"><i class="bi bi-speedometer2"></i> Dashboard Overview</h2>
                <div class="row">
                    <div class="col-md-4">
                        <div class="card text-white bg-primary mb-4">
                            <div class="card-header">
                                <i class="bi bi-people-fill"></i> My Students
                            </div>
                            <div class="card-body">
                                <h5 class="card-title display-4"><?php echo $students_count; ?></h5>
                                <p class="card-text">Total students assigned</p>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="card text-white bg-success mb-4">
                            <div class="card-header">
                                <i class="bi bi-calendar2-check"></i> Today's Sessions
                            </div>
                            <div class="card-body">
                                <h5 class="card-title display-4"><?php echo $today_sessions; ?></h5>
                                <p class="card-text">Sessions scheduled today</p>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="card text-white bg-warning mb-4">
                            <div class="card-header">
                                <i class="bi bi-clock-history"></i> Pending Requests
                            </div>
                            <div class="card-body">
                                <h5 class="card-title display-4"><?php echo $pending_requests; ?></h5>
                                <p class="card-text">Awaiting approval</p>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Class Distribution Card -->
                <div class="card mb-4">
                    <div class="card-header bg-white">
                        <h5 class="mb-0">
                            <i class="bi bi-bar-chart"></i> Class Distribution
                        </h5>
                    </div>
                    <div class="card-body">
                        <?php if ($class_result && $class_result->num_rows > 0): ?>
                            <div class="table-responsive">
                                <table class="table">
                                    <thead class="table-light">
                                        <tr>
                                            <th>Grade/Class</th>
                                            <th>Number of Students</th>
                                            <th>Distribution</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php 
                                        $total_students = 0;
                                        $class_data = [];
                                        while ($row = $class_result->fetch_assoc()) {
                                            $total_students += $row['student_count'];
                                            $class_data[] = $row;
                                        }
                                        foreach ($class_data as $row): 
                                            $percentage = ($row['student_count'] / $total_students) * 100;
                                        ?>
                                            <tr>
                                                <td><?php echo htmlspecialchars($row['grade']); ?></td>
                                                <td><?php echo $row['student_count']; ?></td>
                                                <td>
                                                    <div class="progress">
                                                        <div class="progress-bar bg-primary" 
                                                             role="progressbar" 
                                                             style="width: <?php echo $percentage; ?>%"
                                                             aria-valuenow="<?php echo $percentage; ?>" 
                                                             aria-valuemin="0" 
                                                             aria-valuemax="100">
                                                            <?php echo round($percentage); ?>%
                                                        </div>
                                                    </div>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php else: ?>
                            <div class="text-center py-4">
                                <i class="bi bi-bar-chart text-muted fs-1"></i>
                                <p class="text-muted mt-2">No class distribution data available.</p>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>

                <div class="card">
                    <div class="card-header bg-white">
                        <h5 class="mb-0">
                            <i class="bi bi-calendar3"></i> Upcoming Sessions
                        </h5>
                    </div>
                    <div class="card-body">
                        <?php if ($upcoming_result && $upcoming_result->num_rows > 0): ?>
                            <div class="table-responsive">
                                <table class="table table-hover align-middle">
                                    <thead class="table-light">
                                        <tr>
                                            <th>Student</th>
                                            <th>Date</th>
                                            <th>Time</th>
                                            <th>Duration</th>
                                            <th>Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php while ($row = $upcoming_result->fetch_assoc()): ?>
                                            <tr>
                                                <td>
                                                    <div class="d-flex align-items-center">
                                                        <i class="bi bi-person-circle fs-4 me-2"></i>
                                                        <div>
                                                            <div><?php echo htmlspecialchars($row['student_name']); ?></div>
                                                            <small class="text-muted"><?php echo htmlspecialchars($row['grade']); ?></small>
                                                        </div>
                                                    </div>
                                                </td>
                                                <td><?php echo date('M d, Y', strtotime($row['booking_date'])); ?></td>
                                                <td><?php echo date('g:i A', strtotime($row['booking_time'])); ?></td>
                                                <td><?php echo htmlspecialchars($row['duration']); ?> mins</td>
                                                <td>
                                                    <div class="btn-group">
                                                        <a href="student_list.php?student_id=<?php echo $row['student_id']; ?>" 
                                                           class="btn btn-sm btn-primary">
                                                            <i class="bi bi-eye"></i> View
                                                        </a>
                                                        <a href="session_details.php?id=<?php echo $row['booking_id']; ?>" 
                                                           class="btn btn-sm btn-outline-primary">
                                                            <i class="bi bi-info-circle"></i> Details
                                                        </a>
                                                    </div>
                                                </td>
                                            </tr>
                                        <?php endwhile; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php else: ?>
                            <div class="text-center py-4">
                                <i class="bi bi-calendar-x fs-1 text-muted"></i>
                                <p class="text-muted mt-2">No upcoming sessions scheduled for the next 5 days.</p>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>