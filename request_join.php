<?php
session_start();
require_once 'db_connection.php';

header('Content-Type: application/json');

// Check if user is logged in and is a student
if (!isset($_SESSION['reg_id']) || $_SESSION['role_name'] !== 'student') {
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
    exit();
}

if (!isset($_POST['booking_id'])) {
    echo json_encode(['success' => false, 'message' => 'Booking ID is required']);
    exit();
}

$booking_id = intval($_POST['booking_id']);
$student_id = $_SESSION['reg_id'];

try {
    // Verify booking exists and belongs to student
    $check_query = "SELECT b.*, c.email as counselor_email 
                    FROM tb_bookings b
                    JOIN tb_register c ON b.counselor_id = c.reg_id
                    WHERE b.booking_id = ? AND b.student_id = ? 
                    AND b.status = 'approved' AND b.meeting_link IS NOT NULL";
    
    $stmt = $conn->prepare($check_query);
    $stmt->bind_param("ii", $booking_id, $student_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $booking = $result->fetch_assoc();
    
    if (!$booking) {
        throw new Exception("Invalid booking or unauthorized access");
    }

    // Update booking status to indicate student request
    $update_query = "UPDATE tb_bookings SET student_requested = 1 WHERE booking_id = ?";
    $stmt = $conn->prepare($update_query);
    $stmt->bind_param("i", $booking_id);
    
    if (!$stmt->execute()) {
        throw new Exception("Failed to update request status");
    }

    // Return success with meeting link
    echo json_encode([
        'success' => true,
        'message' => 'Request sent to counselor',
        'meeting_link' => $booking['meeting_link']
    ]);

} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
?>