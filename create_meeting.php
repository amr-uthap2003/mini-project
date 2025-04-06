<?php
session_start();
require_once 'db_connection.php';

// Check if user is logged in and is a counselor
if (!isset($_SESSION['role_name']) || $_SESSION['role_name'] !== 'counselor') {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
    exit();
}

// Check if request is POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
    exit();
}

// Get booking ID and student name from POST data
$booking_id = isset($_POST['booking_id']) ? intval($_POST['booking_id']) : 0;
$student_name = isset($_POST['student_name']) ? $_POST['student_name'] : '';

// Validate booking ID
if ($booking_id <= 0) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Invalid booking ID']);
    exit();
}

$counselor_id = $_SESSION['reg_id'];

// Start transaction
$conn->begin_transaction();

try {
    // Check if the booking exists and is assigned to this counselor
    $check_booking = $conn->prepare("
        SELECT b.*, c.class_name, c.counselor_id 
        FROM tb_bookings b
        JOIN tb_classes c ON b.counselor_id = c.counselor_id
        WHERE b.booking_id = ? AND b.counselor_id = ? AND b.status = 'approved'
    ");
    
    if (!$check_booking) {
        throw new Exception("Database error: " . $conn->error);
    }
    
    $check_booking->bind_param("ii", $booking_id, $counselor_id);
    $check_booking->execute();
    $result = $check_booking->get_result();
    
    if ($result->num_rows === 0) {
        throw new Exception("Booking not found or not authorized");
    }
    
    $booking = $result->fetch_assoc();
    
    // Generate a unique meeting ID using booking ID, timestamp, and a random component
    $timestamp = time();
    $random = substr(md5(mt_rand()), 0, 6);
    $meeting_id = "BM-" . $booking_id . "-" . $timestamp . "-" . $random;
    
    // Generate meeting link
    // For a real implementation, you might integrate with a video conferencing API
    // For now, we'll create a simple link to a hypothetical meeting page
    $meeting_link = "meeting.php?id=" . urlencode($meeting_id);
    
    // Update the booking with the meeting link
    $update_booking = $conn->prepare("
        UPDATE tb_bookings 
        SET meeting_link = ? 
        WHERE booking_id = ?
    ");
    
    if (!$update_booking) {
        throw new Exception("Database error: " . $conn->error);
    }
    
    $update_booking->bind_param("si", $meeting_link, $booking_id);
    if (!$update_booking->execute()) {
        throw new Exception("Failed to update booking with meeting link");
    }
    
    // Log the meeting creation in a separate table if needed
    // This is optional but can be useful for tracking purposes
    if ($conn->query("SHOW TABLES LIKE 'tb_meeting_logs'")->num_rows == 0) {
        // Create the meeting logs table if it doesn't exist
        $conn->query("
            CREATE TABLE tb_meeting_logs (
                log_id INT AUTO_INCREMENT PRIMARY KEY,
                booking_id INT NOT NULL,
                meeting_id VARCHAR(100) NOT NULL,
                counselor_id INT NOT NULL,
                student_id INT NOT NULL,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (booking_id) REFERENCES tb_bookings(booking_id),
                FOREIGN KEY (counselor_id) REFERENCES tb_register(reg_id),
                FOREIGN KEY (student_id) REFERENCES tb_register(reg_id)
            )
        ");
    }
    
    // Insert into meeting logs
    $log_meeting = $conn->prepare("
        INSERT INTO tb_meeting_logs (booking_id, meeting_id, counselor_id, student_id)
        VALUES (?, ?, ?, ?)
    ");
    
    if ($log_meeting) {
        $log_meeting->bind_param("isii", $booking_id, $meeting_id, $counselor_id, $booking['student_id']);
        $log_meeting->execute();
    }
    
    // Commit transaction
    $conn->commit();
    
    // Return success response with meeting link
    header('Content-Type: application/json');
    echo json_encode([
        'success' => true, 
        'meeting_link' => $meeting_link,
        'meeting_id' => $meeting_id,
        'class_name' => $booking['class_name'],
        'student_name' => $student_name
    ]);
    
} catch (Exception $e) {
    // Roll back transaction on error
    $conn->rollback();
    
    // Return error response
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>