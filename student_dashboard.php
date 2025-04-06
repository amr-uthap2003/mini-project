<?php
require_once 'db_connection.php';
requireRole('student');

$student_id = $_SESSION['reg_id'];
$user_details = getUserDetails($conn, $student_id);
if (!$user_details) {
    error_log("Failed to get user details for ID: " . $student_id);
    header("Location: login.php");
    exit;
}

// Get upcoming sessions
$upcoming_sessions = getStudentBookings($conn, $student_id, 'approved');
$pending_sessions = getStudentBookings($conn, $student_id, 'pending');
$total_sessions = count(getStudentBookings($conn, $student_id));

// Get unread messages
$unread_messages = 0;
$unread_messages_query = "SELECT COUNT(*) as count FROM tb_messages WHERE receiver_id = ? AND status = 'unread'";
if ($stmt = $conn->prepare($unread_messages_query)) {
    $stmt->bind_param("i", $student_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $unread_messages = $result->fetch_assoc()['count'];
    $stmt->close();
}

// Get next session
$next_session = null;
$next_session_date = "No upcoming sessions";
foreach ($upcoming_sessions as $session) {
    if ($session['booking_date'] >= date('Y-m-d')) {
        $next_session = $session;
        $next_session_date = date('M d, Y', strtotime($session['booking_date']));
        break;
    }
}

// Get active classes
$active_classes = null;
$active_class_count = 0;
$active_classes_query = "SELECT c.*, b.booking_id, r.username as counselor_name 
                        FROM tb_classes c 
                        INNER JOIN tb_bookings b ON c.class_id = b.class_id 
                        INNER JOIN tb_register r ON c.counselor_id = r.reg_id
                        WHERE b.student_id = ? 
                        AND DATE(c.session_date) = CURDATE() 
                        AND c.status = 'active'";

if ($stmt = $conn->prepare($active_classes_query)) {
    $stmt->bind_param("i", $student_id);
    $stmt->execute();
    $active_classes = $stmt->get_result();
    
    if ($active_classes && $active_classes->num_rows > 0) {
        while ($class = $active_classes->fetch_assoc()) {
            $current_time = strtotime('now');
            $start_time = strtotime($class['session_date'] . ' ' . $class['start_time']);
            $end_time = strtotime($class['session_date'] . ' ' . $class['end_time']);
            if ($current_time >= $start_time && $current_time <= $end_time) {
                $active_class_count++;
            }
        }
        $active_classes->data_seek(0);
    }
    $stmt->close();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Student Dashboard - BrightMind</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.8.1/font/bootstrap-icons.css" rel="stylesheet">
</head>
<body>
    <nav class="navbar navbar-expand-lg navbar-dark bg-success">
        <div class="container">
            <a class="navbar-brand" href="#">
                <i class="bi bi-brightness-high"></i> BrightMind Student
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
                        <a href="student/profile.php" class="list-group-item list-group-item-action">
                            <i class="bi bi-person"></i> My Profile
                        </a>
                        <a href="student/book_session.php" class="list-group-item list-group-item-action">
                            <i class="bi bi-calendar-plus"></i> Book Session
                        </a>
                        <a href="student/my_sessions.php" class="list-group-item list-group-item-action">
                            <i class="bi bi-calendar-check"></i> My Sessions
                        </a>
                        <a href="student/my_classes.php" class="list-group-item list-group-item-action">
                            <i class="bi bi-mortarboard"></i> My Classes
                            <?php if ($active_class_count > 0): ?>
                                <span class="badge bg-success float-end"><?php echo $active_class_count; ?> Active</span>
                            <?php endif; ?>
                        </a>
                    </div>
                </div>
            </div>

            <div class="col-md-9">
                <h2><i class="bi bi-speedometer2"></i> Student Dashboard</h2>
                <div class="row mt-4">
                    <div class="col-md-4">
                        <div class="card text-white bg-primary mb-3">
                            <div class="card-header">Total Sessions</div>
                            <div class="card-body">
                                <h5 class="card-title"><?php echo $total_sessions; ?></h5>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="card text-white bg-success mb-3">
                            <div class="card-header">Next Session</div>
                            <div class="card-body">
                                <h5 class="card-title"><?php echo $next_session_date; ?></h5>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="card text-white bg-info mb-3">
                            <div class="card-header">Messages</div>
                            <div class="card-body">
                                <h5 class="card-title"><?php echo $unread_messages; ?> New</h5>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Active Classes Section -->
                <div class="mt-4">
                    <h3>Today's Classes</h3>
                    <?php if ($active_classes && $active_classes->num_rows > 0): ?>
                        <div class="list-group">
                            <?php while ($class = $active_classes->fetch_assoc()): 
                                $current_time = strtotime('now');
                                $start_time = strtotime($class['session_date'] . ' ' . $class['start_time']);
                                $end_time = strtotime($class['session_date'] . ' ' . $class['end_time']);
                                $is_active = ($current_time >= $start_time && $current_time <= $end_time);
                            ?>
                                <div class="list-group-item">
                                    <div class="d-flex w-100 justify-content-between">
                                        <h5 class="mb-1"><?php echo htmlspecialchars($class['class_name']); ?></h5>
                                        <small class="<?php echo $is_active ? 'text-success' : 'text-muted'; ?>">
                                            <?php echo $is_active ? 'In Session' : 'Scheduled Today'; ?>
                                        </small>
                                    </div>
                                    <p class="mb-1">
                                        With: <?php echo htmlspecialchars($class['counselor_name']); ?><br>
                                        Time: <?php echo date('h:i A', $start_time) . ' - ' . date('h:i A', $end_time); ?>
                                    </p>
                                    <?php if ($is_active): ?>
                                        <a href="student_class_access.php?class_id=<?php echo $class['class_id']; ?>" 
                                           class="btn btn-success btn-sm mt-2">
                                            Join Class
                                        </a>
                                    <?php else: ?>
                                        <button class="btn btn-secondary btn-sm mt-2" disabled>
                                            Class <?php echo $current_time < $start_time ? 'Starts Soon' : 'Ended'; ?>
                                        </button>
                                    <?php endif; ?>
                                </div>
                            <?php endwhile; ?>
                        </div>
                    <?php else: ?>
                        <div class="alert alert-info">
                            No classes scheduled for today.
                        </div>
                    <?php endif; ?>
                </div>

                <!-- Upcoming Sessions Section -->
                <div class="mt-4">
                    <h3>Upcoming Sessions</h3>
                    <?php if (count($upcoming_sessions) > 0): ?>
                        <div class="list-group">
                            <?php foreach (array_slice($upcoming_sessions, 0, 3) as $session): ?>
                                <a href="student/my_sessions.php" class="list-group-item list-group-item-action">
                                    <div class="d-flex w-100 justify-content-between">
                                        <h5 class="mb-1">Session with <?php echo htmlspecialchars($session['counselor_name']); ?></h5>
                                        <small><?php echo date('M d, Y', strtotime($session['booking_date'])); ?></small>
                                    </div>
                                    <p class="mb-1">Time: <?php echo date('h:i A', strtotime($session['booking_time'])); ?>, Duration: <?php echo $session['duration']; ?> mins</p>
                                </a>
                            <?php endforeach; ?>
                        </div>
                    <?php else: ?>
                        <div class="alert alert-info">
                            No upcoming sessions. <a href="student/book_session.php" class="alert-link">Book a session now!</a>
                        </div>
                    <?php endif; ?>
                </div>

                <!-- Pending Sessions Section -->
                <?php if (count($pending_sessions) > 0): ?>
                    <div class="mt-4">
                        <h3>Pending Sessions</h3>
                        <div class="list-group">
                            <?php foreach (array_slice($pending_sessions, 0, 3) as $session): ?>
                                <a href="student/my_sessions.php" class="list-group-item list-group-item-action">
                                    <div class="d-flex w-100 justify-content-between">
                                        <h5 class="mb-1">Session with <?php echo htmlspecialchars($session['counselor_name']); ?></h5>
                                        <small class="text-warning">Pending Approval</small>
                                    </div>
                                    <p class="mb-1">Date: <?php echo date('M d, Y', strtotime($session['booking_date'])); ?>, Time: <?php echo date('h:i A', strtotime($session['booking_time'])); ?></p>
                                </a>
                            <?php endforeach; ?>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>