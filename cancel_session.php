<?php
require_once 'db_connection.php';  // Updated path
requireRole('student');

$student_id = $_SESSION['reg_id'];
$booking_id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);

if (!$booking_id) {
    header("Location: my_sessions.php");
    exit;
}

// Check if the booking belongs to the student
$stmt = $conn->prepare("SELECT * FROM tb_bookings WHERE booking_id = ? AND student_id = ?");
$stmt->bind_param("ii", $booking_id, $student_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    header("Location: my_sessions.php");
    exit;
}

$booking = $result->fetch_assoc();

// Only pending bookings can be cancelled
if ($booking['status'] !== 'pending') {
    header("Location: my_sessions.php");
    exit;
}

// Update the booking status to cancelled
$result = updateBookingStatus($conn, $booking_id, 'cancelled');

if ($result['success']) {
    header("Location: my_sessions.php?cancelled=1");
} else {
    header("Location: my_sessions.php?error=1");
}
exit;
?>