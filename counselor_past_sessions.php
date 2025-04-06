<?php
session_start();
require_once 'db_connection.php';

if (!isset($_SESSION['reg_id']) || $_SESSION['role_id'] != 3) {
    header("Location: login.php");
    exit();
}

$counselor_id = $_SESSION['reg_id'];

// Check table structure
$check_table_query = "SHOW COLUMNS FROM tb_bookings";
$check_result = $conn->query($check_table_query);
$columns = [];
if ($check_result) {
    while ($row = $check_result->fetch_assoc()) {
        $columns[] = $row['Field'];
    }
}

// Get past sessions count
$past_sessions_count = 0;
$count_query = "SELECT COUNT(*) as count FROM tb_bookings 
                WHERE counselor_id = ? 
                AND (booking_date < CURDATE() 
                    OR (booking_date = CURDATE() AND booking_time < CURTIME()))";
$stmt = $conn->prepare($count_query);
$stmt->bind_param("i", $counselor_id);
$stmt->execute();
$count_result = $stmt->get_result();
if ($count_row = $count_result->fetch_assoc()) {
    $past_sessions_count = $count_row['count'];
}
$stmt->close();

// Query for past sessions
$sessions_query = "SELECT b.booking_id, b.booking_date, b.booking_time,
    s.fullname as student_name, s.reg_id as student_id, b.reason as class_name";
    
if (in_array('session_type', $columns)) {
    $sessions_query .= ", b.session_type";
}

$sessions_query .= " FROM tb_bookings b
    JOIN tb_register s ON b.student_id = s.reg_id
    WHERE b.counselor_id = ? 
    AND (b.booking_date < CURDATE() 
        OR (b.booking_date = CURDATE() AND b.booking_time < CURTIME()))
    ORDER BY b.booking_date DESC, b.booking_time DESC";

$stmt = $conn->prepare($sessions_query);
if ($stmt === false) {
    die("Error preparing sessions query: " . $conn->error);
}
$stmt->bind_param("i", $counselor_id);
$stmt->execute();
$sessions_result = $stmt->get_result();
$stmt->close();

$has_session_type = in_array('session_type', $columns);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Past Sessions - Counselor Dashboard</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css" rel="stylesheet">
    <style>
        .nav-tabs { margin-bottom: 20px; border-bottom: 2px solid #dee2e6; }
        .nav-tabs .nav-link { 
            color: #495057; 
            border: none;
            padding: 10px 20px;
        }
        .nav-tabs .nav-link.active { 
            color: #0d6efd;
            font-weight: 500;
            border-bottom: 2px solid #0d6efd;
            margin-bottom: -2px;
        }
    </style>
</head>
<body class="bg-light">
    <?php include 'navbar.php'; ?>

    <div class="container py-4">
        <ul class="nav nav-tabs">
            <li class="nav-item">
                <a class="nav-link" href="counselor_dashboard.php">
                    <i class="bi bi-speedometer2"></i> Dashboard
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link" href="class_management.php">
                    <i class="bi bi-calendar-check"></i> Sessions
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link active" href="counselor_past_sessions.php">
                    <i class="bi bi-clock-history"></i> Past Sessions
                    <?php if ($past_sessions_count > 0): ?>
                        <span class="badge bg-secondary"><?php echo $past_sessions_count; ?></span>
                    <?php endif; ?>
                </a>
            </li>
        </ul>

        <div class="row">
            <div class="col-md-12">
                <div class="card">
                    <div class="card-header bg-white">
                        <h5 class="mb-0">Past Sessions</h5>
                    </div>
                    <div class="card-body">
                        <?php if ($sessions_result && $sessions_result->num_rows > 0): ?>
                            <div class="table-responsive">
                                <table class="table table-hover">
                                    <thead class="table-light">
                                        <tr>
                                            <th>Student</th>
                                            <th>Class</th>
                                            <th>Date & Time</th>
                                            <?php if ($has_session_type): ?>
                                            <th>Type</th>
                                            <?php endif; ?>
                                            <th>Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php while ($session = $sessions_result->fetch_assoc()): ?>
                                            <tr>
                                                <td><?php echo htmlspecialchars($session['student_name']); ?></td>
                                                <td><?php echo htmlspecialchars($session['class_name']); ?></td>
                                                <td>
                                                    <?php 
                                                    echo date('M d, Y', strtotime($session['booking_date'])) . '<br>';
                                                    echo date('h:i A', strtotime($session['booking_time']));
                                                    ?>
                                                </td>
                                                <?php if ($has_session_type): ?>
                                                <td>
                                                    <?php 
                                                    $type_badge = match($session['session_type']) {
                                                        'video' => 'bg-primary',
                                                        'text' => 'bg-success',
                                                        default => 'bg-secondary'
                                                    };
                                                    ?>
                                                    <span class="badge <?php echo $type_badge; ?>">
                                                        <?php echo ucfirst($session['session_type']); ?>
                                                    </span>
                                                </td>
                                                <?php endif; ?>
                                                <td>
                                                    <?php if ($has_session_type && $session['session_type'] === 'text'): ?>
                                                        <a href="chat_history.php?booking_id=<?php echo $session['booking_id']; ?>" 
                                                           class="btn btn-sm btn-outline-primary">
                                                            <i class="bi bi-chat-text"></i> View Chat History
                                                        </a>
                                                    <?php endif; ?>
                                                </td>
                                            </tr>
                                        <?php endwhile; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php else: ?>
                            <div class="text-center py-4">
                                <p class="text-muted">No past sessions found.</p>
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