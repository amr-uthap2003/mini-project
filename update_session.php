<?php
session_start();
require_once 'db_connection.php';

// Check if user is logged in and is a counselor
if (!isset($_SESSION['reg_id']) || $_SESSION['role_id'] != 3) {
    header("Location: login.php");
    exit();
}

$counselor_id = $_SESSION['reg_id'];
$error = "";
$success = "";

// Check if booking ID is provided
if (!isset($_GET['booking_id']) || empty($_GET['booking_id'])) {
    header("Location: class_management.php");
    exit();
}

$booking_id = intval($_GET['booking_id']);

// Verify that this booking belongs to the logged-in counselor
$check_booking_query = "SELECT b.*, s.fullname as student_name, s.reg_id as student_id 
                        FROM tb_bookings b
                        JOIN tb_register s ON b.student_id = s.reg_id
                        WHERE b.booking_id = ? AND b.counselor_id = ? AND b.status = 'approved'";
$stmt = $conn->prepare($check_booking_query);
if ($stmt === false) {
    die("Error preparing booking query: " . $conn->error);
}
$stmt->bind_param("ii", $booking_id, $counselor_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    header("Location: class_management.php");
    exit();
}

$booking = $result->fetch_assoc();
$stmt->close();

// First, check if session_type column exists in the table
$check_column_query = "SHOW COLUMNS FROM tb_bookings LIKE 'session_type'";
$column_result = $conn->query($check_column_query);
$session_type_exists = ($column_result && $column_result->num_rows > 0);

// If the column doesn't exist, create it
if (!$session_type_exists) {
    $add_column_query = "ALTER TABLE tb_bookings ADD COLUMN session_type ENUM('video', 'text') DEFAULT 'text'";
    $conn->query($add_column_query);
    // Set default session type for the current booking
    $booking['session_type'] = 'text';
}

// Process form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_session'])) {
    $new_session_type = $_POST['session_type'];
    
    // Validate session type
    if ($new_session_type !== 'video' && $new_session_type !== 'text') {
        $error = "Invalid session type selected.";
    } else {
        // Update the booking
        $update_query = "UPDATE tb_bookings SET session_type = ? WHERE booking_id = ? AND counselor_id = ?";
        $stmt = $conn->prepare($update_query);
        if ($stmt === false) {
            $error = "Error preparing update query: " . $conn->error;
        } else {
            $stmt->bind_param("sii", $new_session_type, $booking_id, $counselor_id);
            
            if ($stmt->execute()) {
                $success = "Session type updated successfully!";
                // Update the current booking data
                $booking['session_type'] = $new_session_type;
            } else {
                $error = "Failed to update session type: " . $stmt->error;
            }
            $stmt->close();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Update Session</title>
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
                <a class="nav-link active" href="#">
                    <i class="bi bi-gear"></i> Update Session
                </a>
            </li>
        </ul>

        <div class="row justify-content-center">
            <div class="col-md-8">
                <div class="card">
                    <div class="card-header bg-white">
                        <h5 class="mb-0">Update Session Type</h5>
                    </div>
                    <div class="card-body">
                        <?php if (!empty($error)): ?>
                            <div class="alert alert-danger" role="alert">
                                <?php echo $error; ?>
                            </div>
                        <?php endif; ?>
                        
                        <?php if (!empty($success)): ?>
                            <div class="alert alert-success" role="alert">
                                <?php echo $success; ?>
                            </div>
                        <?php endif; ?>

                        <div class="mb-4">
                            <h6>Session Details</h6>
                            <div class="row">
                                <div class="col-md-6">
                                    <p><strong>Student:</strong> <?php echo htmlspecialchars($booking['student_name']); ?></p>
                                    <p><strong>Class/Reason:</strong> <?php echo htmlspecialchars($booking['reason']); ?></p>
                                </div>
                                <div class="col-md-6">
                                    <p><strong>Date:</strong> <?php echo date('F d, Y', strtotime($booking['booking_date'])); ?></p>
                                    <p><strong>Time:</strong> <?php echo date('h:i A', strtotime($booking['booking_time'])); ?></p>
                                </div>
                            </div>
                        </div>

                        <form method="POST" action="">
                            <div class="mb-3">
                                <label class="form-label">Current Session Type:</label>
                                <?php
                                $type_badge = match($booking['session_type'] ?? 'text') {
                                    'video' => 'bg-primary',
                                    'text' => 'bg-success',
                                    default => 'bg-secondary'
                                };
                                ?>
                                <span class="badge <?php echo $type_badge; ?>">
                                    <?php echo ucfirst($booking['session_type'] ?? 'text'); ?>
                                </span>
                            </div>

                            <div class="mb-4">
                                <label for="session_type" class="form-label">New Session Type:</label>
                                <select class="form-select" id="session_type" name="session_type" required>
                                    <option value="">-- Select Type --</option>
                                    <option value="video" <?php if(($booking['session_type'] ?? '') === 'video') echo 'selected'; ?>>
                                        Video Call
                                    </option>
                                    <option value="text" <?php if(($booking['session_type'] ?? '') === 'text') echo 'selected'; ?>>
                                        Text Chat
                                    </option>
                                </select>
                            </div>

                            <div class="d-flex justify-content-between">
                                <a href="class_management.php" class="btn btn-outline-secondary">
                                    <i class="bi bi-arrow-left"></i> Back to Sessions
                                </a>
                                <button type="submit" name="update_session" class="btn btn-primary">
                                    <i class="bi bi-check-lg"></i> Update Session Type
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>