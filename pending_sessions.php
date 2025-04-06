<?php
ob_start();
session_start();
require_once 'db_connection.php';

// Check for counselor access
if (!isset($_SESSION['reg_id']) || !isset($_SESSION['role_id']) || $_SESSION['role_id'] != 2) {
    header("Location: login.php");
    ob_end_flush();
    exit();
}

$counselor_id = $_SESSION['reg_id'];
$error_message = '';
$success_message = '';

// Handle session status updates
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['booking_id']) && isset($_POST['action'])) {
    $booking_id = filter_input(INPUT_POST, 'booking_id', FILTER_VALIDATE_INT);
    $action = filter_input(INPUT_POST, 'action', FILTER_SANITIZE_STRING);
    
    if ($booking_id && ($action === 'accept' || $action === 'reject')) {
        $new_status = ($action === 'accept') ? 'confirmed' : 'rejected';
        
        try {
            $conn->begin_transaction();

            // Check if booking exists and is pending
            $check_stmt = $conn->prepare("SELECT status FROM tb_bookings WHERE booking_id = ? AND counselor_id = ? AND status = 'pending' FOR UPDATE");
            $check_stmt->bind_param("ii", $booking_id, $counselor_id);
            $check_stmt->execute();
            $result = $check_stmt->get_result();

            if ($result->num_rows === 1) {
                // Update booking status
                $update_stmt = $conn->prepare("UPDATE tb_bookings SET status = ?, updated_at = NOW() WHERE booking_id = ? AND counselor_id = ?");
                $update_stmt->bind_param("sii", $new_status, $booking_id, $counselor_id);
                
                if ($update_stmt->execute()) {
                    $conn->commit();
                    $_SESSION['success_message'] = "Session has been " . ucfirst($new_status);
                    header("Location: pending_sessions.php");
                    ob_end_flush();
                    exit();
                } else {
                    throw new Exception("Failed to update session status");
                }
                $update_stmt->close();
            } else {
                throw new Exception("Session no longer available or already processed");
            }
            $check_stmt->close();
        } catch (Exception $e) {
            $conn->rollback();
            $_SESSION['error_message'] = $e->getMessage();
            header("Location: pending_sessions.php");
            ob_end_flush();
            exit();
        }
    }
}

// Get messages from session
if (isset($_SESSION['success_message'])) {
    $success_message = $_SESSION['success_message'];
    unset($_SESSION['success_message']);
}
if (isset($_SESSION['error_message'])) {
    $error_message = $_SESSION['error_message'];
    unset($_SESSION['error_message']);
}

// Fetch pending sessions
$query = "SELECT 
    b.booking_id,
    b.booking_date,
    b.booking_time,
    b.duration,
    b.reason,
    b.session_type,
    b.status,
    r.username as student_name,
    r.email as student_email,
    r.phone as student_phone
    FROM tb_bookings b
    JOIN tb_register r ON b.student_id = r.reg_id
    WHERE b.counselor_id = ? 
    AND b.status = 'pending'
    AND b.booking_date >= CURDATE()
    ORDER BY b.booking_date ASC, b.booking_time ASC";

$pending_sessions = [];
if ($stmt = $conn->prepare($query)) {
    $stmt->bind_param("i", $counselor_id);
    if ($stmt->execute()) {
        $result = $stmt->get_result();
        $pending_sessions = $result->fetch_all(MYSQLI_ASSOC);
    }
    $stmt->close();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Pending Sessions - BrightMind Counselor</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.datatables.net/1.11.5/css/dataTables.bootstrap5.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css">
    <style>
        body { 
            background-color: #f4f6f9; 
            min-height: 100vh;
        }
        .card { 
            box-shadow: 0 4px 6px rgba(0,0,0,0.1); 
            margin-bottom: 20px;
        }
        .table-hover tbody tr:hover { 
            background-color: rgba(0,123,255,0.1); 
        }
        .sidebar {
            min-height: 100vh;
            border-right: 1px solid #dee2e6;
            background-color: #fff;
        }
        .btn-group .btn {
            margin-right: 5px;
        }
        .nav-link {
            color: #495057;
            padding: 0.8rem 1rem;
            border-radius: 0.25rem;
            margin: 0.2rem 0;
        }
        .nav-link:hover {
            background-color: #e9ecef;
        }
        .nav-link.active {
            color: #007bff;
            background-color: #e9ecef;
        }
        .alert {
            margin-bottom: 1rem;
        }
        .badge {
            padding: 0.5em 0.8em;
        }
        .dropdown-menu {
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
    </style>
</head>

<body>
    <div class="container-fluid">
        <div class="row">
            <!-- Sidebar -->
            <nav class="col-md-2 d-md-block bg-light sidebar">
                <div class="position-sticky pt-3">
                    <div class="navbar-brand mb-4 text-center">
                        <img src="logo.png" alt="BrightMind" class="img-fluid" style="max-width: 150px;">
                    </div>
                    <ul class="nav flex-column">
                        <li class="nav-item">
                            <a class="nav-link" href="counselor_dashboard.php">
                                <i class="fas fa-home me-2"></i>Dashboard
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="student_list.php">
                                <i class="fas fa-users me-2"></i>Student List
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="manage_availability.php">
                                <i class="fas fa-calendar-alt me-2"></i>Manage Availability
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link active" href="pending_sessions.php">
                                <i class="fas fa-clock me-2"></i>Pending Sessions
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="my_profile.php">
                                <i class="fas fa-user me-2"></i>My Profile
                            </a>
                        </li>
                        <li class="nav-item mt-3">
                            <a class="nav-link text-danger" href="logout.php">
                                <i class="fas fa-sign-out-alt me-2"></i>Logout
                            </a>
                        </li>
                    </ul>
                </div>
            </nav>

            <!-- Main Content -->
            <main class="col-md-10 ms-sm-auto px-4 py-3">
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <h1 class="h2">Pending Sessions</h1>
                    <div class="dropdown">
                        <button class="btn btn-secondary dropdown-toggle" type="button" id="userDropdown" data-bs-toggle="dropdown">
                            <i class="fas fa-user me-2"></i><?php echo htmlspecialchars($_SESSION['username']); ?>
                        </button>
                        <ul class="dropdown-menu dropdown-menu-end">
                            <li><a class="dropdown-item" href="my_profile.php"><i class="fas fa-user-circle me-2"></i>Profile</a></li>
                            <li><hr class="dropdown-divider"></li>
                            <li><a class="dropdown-item text-danger" href="logout.php"><i class="fas fa-sign-out-alt me-2"></i>Logout</a></li>
                        </ul>
                    </div>
                </div>

                <!-- Success/Error Messages -->
                <?php if ($success_message): ?>
                    <div class="alert alert-success alert-dismissible fade show" role="alert">
                        <i class="fas fa-check-circle me-2"></i><?php echo htmlspecialchars($success_message); ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>

                <?php if ($error_message): ?>
                    <div class="alert alert-danger alert-dismissible fade show" role="alert">
                        <i class="fas fa-exclamation-circle me-2"></i><?php echo htmlspecialchars($error_message); ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>

                <!-- Pending Sessions Table -->
                <div class="card">
                    <div class="card-body">
                        <?php if (empty($pending_sessions)): ?>
                            <div class="alert alert-info text-center">
                                <i class="fas fa-info-circle me-2"></i>No pending sessions at the moment.
                            </div>
                        <?php else: ?>
                            <div class="table-responsive">
                                <table id="sessionsTable" class="table table-hover">
                                    <thead class="table-light">
                                        <tr>
                                            <th>Student</th>
                                            <th>Date & Time</th>
                                            <th>Duration</th>
                                            <th>Session Type</th>
                                            <th>Reason</th>
                                            <th>Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($pending_sessions as $session): ?>
                                            <tr>
                                                <td>
                                                    <strong><?php echo htmlspecialchars($session['student_name']); ?></strong><br>
                                                    <small class="text-muted">
                                                        <i class="fas fa-envelope me-1"></i><?php echo htmlspecialchars($session['student_email']); ?>
                                                        <?php if (isset($session['student_phone'])): ?>
                                                            <br><i class="fas fa-phone me-1"></i><?php echo htmlspecialchars($session['student_phone']); ?>
                                                        <?php endif; ?>
                                                    </small>
                                                </td>
                                                <td>
                                                    <i class="fas fa-calendar me-1"></i><?php echo date('M d, Y', strtotime($session['booking_date'])); ?><br>
                                                    <i class="fas fa-clock me-1"></i><?php echo date('h:i A', strtotime($session['booking_time'])); ?>
                                                </td>
                                                <td><i class="fas fa-hourglass-half me-1"></i><?php echo $session['duration']; ?> mins</td>
                                                <td>
                                                    <span class="badge bg-primary">
                                                        <i class="fas fa-<?php echo $session['session_type'] === 'video' ? 'video' : ($session['session_type'] === 'text' ? 'comments' : 'person'); ?> me-1"></i>
                                                        <?php echo ucfirst($session['session_type']); ?>
                                                    </span>
                                                </td>
                                                <td><?php echo htmlspecialchars($session['reason']); ?></td>
                                                <td>
                                                    <div class="btn-group" role="group">
                                                        <form action="<?php echo $_SERVER['PHP_SELF']; ?>" method="post" class="d-inline" onsubmit="return confirm('Are you sure you want to accept this session?');">
                                                            <input type="hidden" name="booking_id" value="<?php echo $session['booking_id']; ?>">
                                                            <input type="hidden" name="action" value="accept">
                                                            <button type="submit" class="btn btn-success btn-sm">
                                                                <i class="fas fa-check me-1"></i>Accept
                                                            </button>
                                                        </form>
                                                        <form action="<?php echo $_SERVER['PHP_SELF']; ?>" method="post" class="d-inline" onsubmit="return confirm('Are you sure you want to reject this session?');">
                                                            <input type="hidden" name="booking_id" value="<?php echo $session['booking_id']; ?>">
                                                            <input type="hidden" name="action" value="reject">
                                                            <button type="submit" class="btn btn-danger btn-sm">
                                                                <i class="fas fa-times me-1"></i>Reject
                                                            </button>
                                                        </form>
                                                    </div>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </main>
        </div>
    </div>

    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.datatables.net/1.11.5/js/jquery.dataTables.min.js"></script>
    <script src="https://cdn.datatables.net/1.11.5/js/dataTables.bootstrap5.min.js"></script>
    <script>
        $(document).ready(function() {
            $('#sessionsTable').DataTable({
                pageLength: 10,
                order: [[1, 'asc']],
                language: {
                    search: "Search sessions:"
                }
            });

            // Auto-hide alerts after 5 seconds
            setTimeout(function() {
                $('.alert').alert('close');
            }, 5000);
        });
    </script>
</body>
</html>