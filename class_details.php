<?php
session_start();
require_once 'db_connection.php';

if (!isset($_SESSION['role_name']) || $_SESSION['role_name'] !== 'counselor') {
    header("Location: login.php");
    exit();
}

$counselor_id = isset($_SESSION['reg_id']) ? (int)$_SESSION['reg_id'] : 0;
$class_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$success_message = isset($_GET['status_updated']) ? "Student status updated successfully!" : '';
$students_result = null;

try {
    if (!$conn) {
        throw new Exception("Database connection failed");
    }

    if ($class_id <= 0 || $counselor_id <= 0) {
        throw new Exception("Invalid class or counselor ID");
    }

    $class_query = "SELECT c.*, 
                    (SELECT COUNT(*) FROM tb_bookings WHERE class_id = c.class_id) as student_count 
                    FROM tb_classes c 
                    WHERE c.class_id = ? AND c.counselor_id = ?";
    
    $stmt = $conn->prepare($class_query);
    if (!$stmt) {
        throw new Exception("Query preparation failed: " . $conn->error);
    }
    
    $stmt->bind_param("ii", $class_id, $counselor_id);
    if (!$stmt->execute()) {
        throw new Exception("Query execution failed: " . $stmt->error);
    }
    
    $class_result = $stmt->get_result();
    $class = $class_result->fetch_assoc();

    if (!$class) {
        throw new Exception("Class not found or access denied.");
    }
    $students_query = "SELECT s.reg_id, s.name, s.email, b.booking_date, b.status as booking_status, b.booking_id
                      FROM tb_bookings b
                      JOIN tb_register s ON b.student_id = s.reg_id
                      WHERE b.class_id = ? AND b.deleted_at IS NULL
                      ORDER BY b.booking_date DESC";
    
    $stmt = $conn->prepare($students_query);
    if (!$stmt) {
        throw new Exception("Student query preparation failed: " . $conn->error);
    }
    
    $stmt->bind_param("i", $class_id);
    if (!$stmt->execute()) {
        throw new Exception("Student query execution failed: " . $stmt->error);
    }
    
    $students_result = $stmt->get_result();
    $stmt->close();

    


} catch (Exception $e) {
    $error_message = $e->getMessage();
    error_log("Class Details Error: " . $e->getMessage());
}

if (isset($_POST['update_status'])) {
    try {
        $booking_id = (int)$_POST['booking_id'];
        $new_status = $_POST['status'];
        
        if (!in_array($new_status, ['active', 'inactive'])) {
            throw new Exception("Invalid status value");
        }

        $update_sql = "UPDATE tb_bookings SET status = ? WHERE booking_id = ? AND class_id = ?";
        $stmt = $conn->prepare($update_sql);
        if (!$stmt) {
            throw new Exception("Status update preparation failed: " . $conn->error);
        }

        $stmt->bind_param("sii", $new_status, $booking_id, $class_id);
        if (!$stmt->execute()) {
            throw new Exception("Failed to update student status: " . $stmt->error);
        }

        header("Location: class_details.php?id=" . $class_id . "&status_updated=1");
        exit();
    } catch (Exception $e) {
        $error_message = $e->getMessage();
        error_log("Status Update Error: " . $e->getMessage());
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Class Details - BrightMind</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.8.1/font/bootstrap-icons.css" rel="stylesheet">
    <style>
        .badge { font-size: 0.9em; padding: 0.5em 0.8em; }
        .btn-group .btn { margin-right: 2px; }
        .status-select { min-width: 100px; }
    </style>
</head>
<body class="bg-light">
    <nav class="navbar navbar-expand-lg navbar-dark bg-primary">
        <div class="container">
            <a class="navbar-brand" href="counselor_dashboard.php">
                <i class="bi bi-brightness-high"></i> BrightMind Counselor
            </a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav ms-auto">
                    <li class="nav-item">
                        <a class="nav-link" href="counselor_dashboard.php">
                            <i class="bi bi-speedometer2"></i> Dashboard
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="class_management.php">
                            <i class="bi bi-mortarboard"></i> Classes
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="messages.php">
                            <i class="bi bi-chat-dots"></i> Messages
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="logout.php">
                            <i class="bi bi-box-arrow-right"></i> Logout
                        </a>
                    </li>
                </ul>
            </div>
        </div>
    </nav>

    <div class="container mt-4">
        <?php if (isset($error_message)): ?>
            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                <i class="bi bi-exclamation-triangle"></i> <?php echo htmlspecialchars($error_message); ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        <?php endif; ?>

        <?php if (!empty($success_message)): ?>
            <div class="alert alert-success alert-dismissible fade show" role="alert">
                <i class="bi bi-check-circle"></i> <?php echo htmlspecialchars($success_message); ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        <?php endif; ?>

        <?php if (isset($class)): ?>
        <div class="row">
            <div class="col-md-4">
                <div class="card mb-4">
                    <div class="card-header bg-primary text-white">
                        <h5 class="mb-0"><i class="bi bi-info-circle"></i> Class Information</h5>
                    </div>
                    <div class="card-body">
                        <h4><?php echo htmlspecialchars($class['class_name']); ?></h4>
                        <p class="text-muted"><?php echo htmlspecialchars($class['description']); ?></p>
                        
                        <div class="mb-3">
                            <strong>Session Type:</strong>
                            <?php 
                            $type_badge = match($class['session_type']) {
                                'video' => 'bg-primary',
                                'text' => 'bg-success',
                                'in-person' => 'bg-warning',
                                default => 'bg-secondary'
                            };
                            ?>
                            <span class="badge <?php echo $type_badge; ?>">
                                <?php echo ucfirst($class['session_type']); ?>
                            </span>
                        </div>

                        <?php if ($class['session_type'] === 'video' && !empty($class['meeting_link'])): ?>
                        <div class="mb-3">
                            <strong>Meeting Link:</strong><br>
                            <a href="<?php echo htmlspecialchars($class['meeting_link']); ?>" 
                               target="_blank" class="btn btn-sm btn-outline-primary mt-2">
                                <i class="bi bi-camera-video"></i> Join Meeting
                            </a>
                        </div>
                        <?php endif; ?>

                        <div class="mb-3">
                            <strong>Created:</strong><br>
                            <?php echo date('F j, Y g:i A', strtotime($class['created_at'])); ?>
                        </div>

                        <div class="mb-3">
                            <strong>Status:</strong>
                            <span class="badge bg-<?php echo $class['status'] === 'active' ? 'success' : 'danger'; ?>">
                                <?php echo ucfirst($class['status']); ?>
                            </span>
                        </div>

                        <div class="btn-group">
                            <a href="edit_class.php?id=<?php echo $class_id; ?>" 
                               class="btn btn-primary">
                                <i class="bi bi-pencil"></i> Edit Class
                            </a>
                            <a href="class_session.php?id=<?php echo $class_id; ?>" 
                               class="btn btn-success">
                                <i class="bi bi-play-circle"></i> Start Session
                            </a>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-md-8">
                <div class="card">
                    <div class="card-header bg-white d-flex justify-content-between align-items-center">
                        <h5 class="mb-0">
                            <i class="bi bi-people"></i> Enrolled Students 
                            <span class="badge bg-primary"><?php echo $class['student_count']; ?></span>
                        </h5>
                    </div>
                    <div class="card-body">
                        <?php if ($students_result && $students_result->num_rows > 0): ?>
                            <div class="table-responsive">
                                <table class="table table-hover">
                                    <thead class="table-light">
                                        <tr>
                                            <th>Name</th>
                                            <th>Email</th>
                                            <th>Enrolled Date</th>
                                            <th>Status</th>
                                            <th>Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php while ($student = $students_result->fetch_assoc()): ?>
                                            <tr>
                                                <td><?php echo htmlspecialchars($student['name']); ?></td>
                                                <td><?php echo htmlspecialchars($student['email']); ?></td>
                                                <td><?php echo date('M j, Y', strtotime($student['booking_date'])); ?></td>
                                                <td>
                                                    <form method="POST" action="" class="d-inline">
                                                        <input type="hidden" name="booking_id" 
                                                               value="<?php echo $student['booking_id']; ?>">
                                                        <select name="status" 
                                                                class="form-select form-select-sm d-inline-block status-select" 
                                                                onchange="this.form.submit()">
                                                            <option value="active" 
                                                                <?php echo $student['booking_status'] === 'active' ? 'selected' : ''; ?>>
                                                                Active
                                                            </option>
                                                            <option value="inactive" 
                                                                <?php echo $student['booking_status'] === 'inactive' ? 'selected' : ''; ?>>
                                                                Inactive
                                                            </option>
                                                        </select>
                                                        <input type="hidden" name="update_status" value="1">
                                                    </form>
                                                </td>
                                                <td>
                                                    <div class="btn-group">
                                                        <a href="student_profile.php?id=<?php echo $student['reg_id']; ?>" 
                                                           class="btn btn-sm btn-outline-primary">
                                                            <i class="bi bi-person"></i> Profile
                                                        </a>
                                                        <a href="message.php?student_id=<?php echo $student['reg_id']; ?>" 
                                                           class="btn btn-sm btn-outline-success">
                                                            <i class="bi bi-chat"></i> Message
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
                                <i class="bi bi-people text-muted fs-1"></i>
                                <p class="text-muted mt-2">No students enrolled yet.</p>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
        <?php endif; ?>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>